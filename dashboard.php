<?php 

require_once('Controller.class.php');

class Dashboard extends Controller
{
        public function run() 
        {

            // Accès invité
            if ('enregistrer_temporairement' ===  $_POST['action']) {
                // Connexion invitée
                getSession('', true);
            }

            if (getUtilisateurId() === '') {
                header('Location: index.php');
                return;
            }

            $params = array();
            $creation = false;
            // Nouveau message
            if (isset($_POST['action'])) {
                if (isSerieux() && 'report' ===  $_POST['action']) {
                    $mysqli = shaarliMyConnect();
                    $messageEntite = creerMessage(getUtilisateurId(), $_POST['message']);
                    $retourInsertion = insertEntite($mysqli, 'message', $messageEntite);
                    if ($retourInsertion) {
                        $params['message'] = 'Le message a bien été envoyé';
                    } else {
                        $params['message'] = "L'envoi du message a échoué...";
                    }
                }

                if ('mon_shaarli' ===  $_POST['action'] && !isEnAttenteDeModeration()) {
                    if (isset($_POST['shaarli_url'])) {
                        if (!isShaarliste()) {
                            // Premiere soumission du shaarli, il passera par la modération
                            majShaarliUrl($_POST['shaarli_url']);
                        } else {
                            // Deuxieme soumission, on vérifie le flux de prime abord
                            $url = $_POST['shaarli_url'];
                            $url = supprimeDernierPointInterrogation($url);
                            if ($url !== getShaarliUrl()) {
                                $content = getRss($url . '?do=rss');
                                if (empty($content)) {
                                    $params['message'] = "Ce flux n'a pu être récupéré...";
                                } else {
                                    majShaarliUrl($_POST['shaarli_url'], false);
                                }
                            }
                        }
                    }
                }
                // Enregistrement d'un nouveau profil
                if ('enregistrer' ===  $_POST['action']) {
                    $password = '';
                    if (isset($_POST['password'])) {
                        $password = $_POST['password'];
                    }
                    $session = getSession($_POST['profil_id'], false, $password);
                    $abonnements = $_POST['shaarlistes'];
                    majAbonnements($abonnements);
                    $params['message'] = "Votre profil vient d'être créé";
                }

                // Enregistrement d'un password
                if ('enregistrer_password' ===  $_POST['action']) {
                    $password = '';
                    if (isset($_POST['password'])) {
                        $password = $_POST['password'];
                    }
                    $session = updatePassword(getUtilisateurId(), $password);

                    $params['message'] = "Le pwd est enregistré";
                }

                // Maj filtre des tags
                if ('enregistrer_tags' ===  $_POST['action']) {
                    $session = getSession($_POST['profil_id']);
                    updateTags($_POST['tags']);
                    updateNotAllowedTags($_POST['not_allowed_tags']);
                    $params['message'] = "La liste des tags à ignorer a été mise à jour";
                }
                // Maj filtre des urls
                if ('enregistrer_urls' ===  $_POST['action']) {
                    $session = getSession($_POST['profil_id']);
                    updateNotAllowedUrls($_POST['not_allowed_urls']);
                    $params['message'] = "La liste des sites web à ignorer a été mise à jour";
                }
            }

            if (isset($_GET['action'])) {
                // Connexion
                if ('connexion' ===  $_GET['action']) {
                        $password = null; 
                        if (isset($_POST['password'])) {
                            $password = $_POST['password'];
                        }
                        $session = getSession($_GET['profil_id'], true, $password);
                        if ($session !== 401) {
                            setcookie('shaarlieur', $_GET['profil_id'], time()+31536000, '.shaarli.fr');
                            header('Location: index.php');
                            return;
                        }

                        $params['message'] = "Ce compte est protégé par un mot de passe";
                        $params['demande_password'] = "Ce compte est protégé par un mot de passe";
                }

                // Création d'un nouveau profil
                if ('creation' ===  $_GET['action']) {
                    $creation = true;
                }
            }

            if (empty(getAbonnements())) {
                $pasDeProfil = true;
            }


            $params['shaarlieurPositionTop'] = getShaarlieurPositionTop();
            $infoAboutAll = file_get_contents('http://shaarli.fr/api.php?do=getInfoAboutAll');
            $infoAboutAll = remove_utf8_bom($infoAboutAll);
            $infoAboutAllDecoded = json_decode($infoAboutAll, true);
            $infoAboutAllDecodedChunked = array_chunk($infoAboutAllDecoded['stat'],  4);
            $params['infoAboutAllDecodedChunked'] = $infoAboutAllDecodedChunked;
            $params['abonnements'] = array();
            
            $params['id_rss'] = getIdRss();
            $params['shaarli_url'] = getShaarliUrl();
            $params['pas_de_profil'] = $pasDeProfil;
            $params['creation'] = $creation;
            $params['currentBadge'] = getCurrentBadge();

            $this->render($params);
        }

        public function render($params=array())
        {
                ?><!doctype html>
                <html class="no-js" lang="en">
                        <?php
                        $this->renderHead();
                    ?>

                    <body>
                    <?php
                    if (!$params['pas_de_profil'] && !$params['creation']) {
                        $this->renderMenu();
                    }
                    ?>
                        <?php
                        if (isset($params['message'])) {
                            ?>
                            <div class="row">
                                <div class="columns large-12 center">
                                    <div class="panel">
                                            <span class="color-success"><?php echo $params['message'];?></span>
                                        </div>
                                </div>
                            </div>
                            <?php
                        }
                        
                        if (isset($params['demande_password'])) {
                        ?>
                            <div class="row" data-equalizer>
                                <div class="columns large-6 text-center">
                                    <div class="panel" data-equalizer-watch>
                                        <div class="row">
                                            <div class="columns large-12">
                                                <h3>Mot de passe</h3>
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="connexion"/>
                                                    <input type="hidden" name="profil_id" value="<?php echo htmlentities($_GET['profil_id']); ?>"/>
                                                    <input name="password" type="password" value=""/>
                                                    <input class="button success" type="submit" value="Valider" />
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php
                        } else {
                            ?>
                            <?php
                            if ($params['pas_de_profil'] && !$params['creation']) {
                            ?>
                            <div class="row">
                                <div class="column large-12 text-center">
                                    <h1>Bienvenue sur shaarli.fr</h1>
                                </div>
                            </div>
                            <div class="row" data-equalizer>
                                <div class="columns large-6 text-center">
                                    <div class="panel" data-equalizer-watch>
                                        <div class="row">
                                            <div class="columns large-12">
                                                <h3>Charger un profil</h3>
                                                <form method="GET">
                                                    <input type="hidden" name="action" value="connexion"/>
                                                    <input name="profil_id" type="text" value=""/>
                                                    <input class="button success" type="submit" value="Charger profil" />
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="columns large-6 ">
                                    <div class="panel" data-equalizer-watch>
                                        <div class="row hide-for-large-up">
                                            <div class="columns large-12 text-center">
                                                <a href="?action=creation" class="button success">Créer un profil</a>
                                                <form id="form-abonnements" method="POST">
                                                    <input type="hidden" name="action" value="enregistrer_temporairement"/>
                                                    <input type="hidden" name="profil_id" value=""/>
                                                    <input id="button-tous" type="submit" class="button " value="Accès invité" />
                                                </form>
                                            </div>
                                        </div>
                                        <div class="row valign-center show-for-large-up">
                                            <div class="columns large-12 text-center">
                                                <a href="?action=creation" class="button success">Créer un profil</a>
                                                <form id="form-abonnements" method="POST">
                                                    <input type="hidden" name="action" value="enregistrer_temporairement"/>
                                                    <input type="hidden" name="profil_id" value=""/>
                                                    <input id="button-tous" type="submit" class="button " value="Accès invité" />
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                        ?>

                        <?php
                         if (!$params['pas_de_profil'] && !$params['creation']) {
                            ?>
                            <div class="row">
                                <div class="columns large-12">
                                    <div class="panel">

                                        <div class="row">
                                        <?php
                                            /*
                                             * Bloc image de profil
                                             */
                                            if (isShaarliste()) {
                                                $imageProfilSrc = getImageProfilSrc();
                                                if (!is_null($imageProfilSrc)) {
                                                ?>
                                                    <div class="columns large-2 medium-3 small-4 text-right">
                                                        <img class="profil-avatar" src="<?php echo $imageProfilSrc; ?>" alt="image de profil" />
                                                    </div>
                                                <?php
                                                }
                                            }
                                            ?>
                                            <div class="column large-10 medium-9 small-8">
                                                <h1><?php echo getUtilisateurId(); ?></h1>
                                            </div>
                                        </div>
                                        
                                        <hr/>
                                        <div class="row text-center">
                                            <div class="columns large-12">
                                                <?php
                                                /*
                                                 * Bloc top shaarlieur
                                                 */
                                                $idBadge = 1;
                                                if (!empty($params['shaarlieurPositionTop']) && is_file(sprintf('img/top/top_%s.gif', $params['shaarlieurPositionTop']))) {
                                                    $idBadge = $params['shaarlieurPositionTop'];
                                                }
                                                if (!is_null($params['currentBadge'])) {
                                                    $idBadge = $params['currentBadge'];
                                                }

                                                $nomImageTopGif = sprintf('img/top/top_%s.gif', $idBadge);
                                                if (is_file($nomImageTopGif)) {
                                                    ?><a href="badges.php"><img src="<?php echo $nomImageTopGif; ?>" alt="top_gif"/></a><?php
                                                }
                                                ?>
                                            </div>
                                            <?php 
                                            if (!empty($params['shaarlieurPositionTop']) && $params['shaarlieurPositionTop'] <= 1000) {
                                            ?>
                                            <div class="columns large-12">
                                                <?php
                                                if ($params['shaarlieurPositionTop'] == 1) {
                                                    ?>Vous êtes l'utilisateur <span title="Vous êtes le <?php echo $params['shaarlieurPositionTop']; ?>°" class="top-orange">le plus fidèle</span> du site !!!<?php
                                                } elseif ($params['shaarlieurPositionTop'] <= 5) {
                                                    ?>Vous êtes l'un des cinq utilisateurs <span title="Vous êtes le <?php echo $params['shaarlieurPositionTop']; ?>°" class="top-orange">les plus fidèles</span> du site !<?php
                                                } elseif ($params['shaarlieurPositionTop'] <= 10) {
                                                    ?>Vous faites partie du <span title="Vous êtes le <?php echo $params['shaarlieurPositionTop']; ?>°" class="top-orange">top 10</span> des utilisateurs du site :-D<?php
                                                } elseif ($params['shaarlieurPositionTop'] <= 50) {
                                                    ?>Vous faites actuellement partie du <span title="Vous êtes le <?php echo $params['shaarlieurPositionTop']; ?>°" class="top-orange">top 50</span> des utilisateurs du site :-)<?php
                                                } elseif ($params['shaarlieurPositionTop'] <= 100) {
                                                    ?>Vous faites actuellement partie du <span title="Vous êtes le <?php echo $params['shaarlieurPositionTop']; ?>°" class="top-orange">top 100</span> des utilisateurs du site :-)<?php
                                                } else {
                                                    ?>Vous faites actuellement partie du <span title="Vous êtes le <?php echo $params['shaarlieurPositionTop']; ?>°" class="top-orange">top 1000</span> des utilisateurs du site :-)<?php
                                                }
                                                ?>
                                            </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                        ?>

                        <?php
                        if (!$params['pas_de_profil'] && !$params['creation']) {
                        ?>
                        <div class="row">
                            <div class="columns large-12 center">
                                <div class="panel">
                                    <div class="row">
                                        <div class="columns large-12">
                                            <p><a href="abonnements.php">Gérer mes abonnements</a></p>
                                            <?php if (!is_null($params['id_rss'])) { ?>
                                                <p><a href="index.php?q=shaarli:<?php echo $params['id_rss']; ?>">Messagerie</a></p>
                                            <?php } ?>
                                            <?php if (isShaarliste()) { ?>
                                                <p><span class="fake-a" id="button-synchro-shaarli">Synchroniser mon shaarli</span> <img class="hidden" id="img-synchro-shaarli" src="img/spinner-24-24.gif"></p>
                                                <p><span class="fake-a" id="button-synchro-favicon">Synchroniser ma favicon</span> <img class="hidden" id="img-synchro-favicon" src="img/spinner-24-24.gif"></p>
                                                <?php if (!isEnAttenteDeModeration()) { ?>
                                                    <p><a class="link-show" href="#modifier-shaarli">Modifier mon shaarli</a></p>
                                                <?php } else { ?>
                                                    <p><em>Shaarli en attente de modération</em></p>
                                                <?php } ?>
                                            <?php } else { ?>
                                                <?php if (!isEnAttenteDeModeration()) { ?>
                                                    <p><a class="link-show" href="#mon-shaarli">Ajouter mon shaarli à ce site web</a></p>
                                                <?php } else { ?>
                                                    <p><em>Shaarli en attente de modération</em></p>
                                                <?php } ?>
                                            <?php }  ?>
                                                <p><a class="link-show" href="#pwd">Modifier le mot de passe du profil</a></p>
                                                <p><a class="link-show" href="#options">Gérer mes paramètres</a></p>
                                            
                                            <?php if (isSerieux()) { ?>
                                                <p><a class="link-show" href="#report">Reporter un bug/commentaire/conseil</a></p>
                                                <p><a class="link-show" href="#filtres">Filter les articles selon plusieurs critères</a></p>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                        <?php
                        if (false && !$params['pas_de_profil'] && !$params['creation']) {
                        ?>
                        <div class="row">
                            <div class="columns large-12 center">
                                <div class="panel">
                                    <div class="row">
                                        <div class="columns large-12">
                                            <h3>Import/Export profil</h3>
                                            <p>Copier coller cette clef pour charger ce profil dans un autre navigateur</p>
                                            <form method="GET">
                                                    <input type="hidden" name="action" value="connexion"/>
                                                    <input name="profil_id" type="text" value="<?php echo htmlentities(getUtilisateurId());?>"/>
                                                    <input class="button" type="submit" value="Charger profil" />
                                            </form>
                                            <a href="https://www.shaarli.fr/dashboard.php?action=connexion&amp;profil_id=<?php echo htmlentities(getUtilisateurId());?>">Url de connexion</a>
                                            <p class="astuce">Astuce : remplacez la clef par quelque chose de simple à retenir</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php } ?>

                        <?php
                        if (!$params['pas_de_profil']) {
                        ?>
                        <div class="row bloc-show" style="display:none;" id="options">
                            <div class="columns large-12 center">
                                <div class="panel">
                                    <div class="row">
                                        <div class="column large-12">
                                            <h3>Paramètres</h3>
                                        </div>
                                    </div>
                                    <br/>
                                    <div class="row">
                                        <div class="column large-12">
                                            <h5>Options fonctionnelles</h5>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="columns large-8">
                                            <span>Afficher les commentaires des shaarlistes non suivis</span>
                                        </div>
                                        <div class="columns large-4">
                                                <input type="radio" <?php if(displayShaarlistesNonSuivis()) {echo ' checked="checked" ';}?> name="checkbox-shaarlistes-suivis" class="checkbox-display-shaarlistes-non-suivis no-margin" value="oui"/>oui
                                            <input type="radio" <?php if(!displayShaarlistesNonSuivis()) {echo ' checked="checked" ';}?> name="checkbox-shaarlistes-suivis" class="checkbox-display-shaarlistes-non-suivis no-margin" value="non"/>non
                                        </div>
                                    </div>
                                    <hr class="no-margin"/>
                                    <div class="row">
                                        <div class="columns large-8">
                                            <span>Afficher le bloc "En ce moment sur la shaarlisphère"</span>
                                        </div>
                                        <div class="columns large-4">
                                            <input type="radio" <?php if(displayBestArticle()) {echo ' checked="checked" ';}?> name="checkbox-display-best-article" class="checkbox-display-best-article no-margin" value="oui"/>oui
                                            <input type="radio" <?php if(!displayBestArticle()) {echo ' checked="checked" ';}?> name="checkbox-display-best-article" class="checkbox-display-best-article no-margin" value="non"/>non
                                        </div>
                                    </div>
                                    <hr class="no-margin"/>
                                    <?php if ($params['shaarli_url']) { ?>
                                    <div class="row">
                                        <div class="columns large-8">
                                            <span>Afficher le bloc de conversation</span>
                                        </div>
                                        <div class="columns large-4">
                                            <input type="radio" <?php if(displayBlocConversation()) {echo ' checked="checked" ';}?> name="checkbox-display_bloc_conversation" class="checkbox-display_bloc_conversation no-margin" value="oui"/>oui
                                            <input type="radio" <?php if(!displayBlocConversation()) {echo ' checked="checked" ';}?> name="checkbox-display_bloc_conversation" class="checkbox-display_bloc_conversation no-margin" value="non"/>non
                                        </div>
                                    </div>
                                    <?php } ?>
                                    <hr class="no-margin"/>
                                    <div class="row">
                                        <div class="columns large-8">
                                            <span>Afficher les liens sans description</span>
                                        </div>
                                        <div class="columns large-4">
                                            <input type="radio" <?php if(displayEmptyDescription()) {echo ' checked="checked" ';}?> name="checkbox-display-empty-description" class="checkbox-display-empty-description no-margin" value="oui"/>oui
                                            <input type="radio" <?php if(!displayEmptyDescription()) {echo ' checked="checked" ';}?> name="checkbox-display-empty-description" class="checkbox-display-empty-description no-margin" value="non"/>non
                                        </div>
                                    </div>
                                    <hr class="no-margin"/>
                                    <div class="row">
                                        <div class="columns large-8">
                                            <span>Afficher uniquement les nouveaux liens du jour</span>
                                        </div>
                                        <div class="columns large-4">
                                            <input type="radio" <?php if(displayOnlyNewArticles()) {echo ' checked="checked" ';}?> name="checkbox-display_only_new_articles" class="checkbox-display_only_new_articles" value="oui"/>oui
                                            <input type="radio" <?php if(!displayOnlyNewArticles()) {echo ' checked="checked" ';}?> name="checkbox-display_only_new_articles" class="checkbox-display_only_new_articles" value="non"/>non
                                        </div>
                                    </div>
                                    <hr class="no-margin"/>
                                    <div class="row">
                                        <div class="columns large-8">
                                            <span>Afficher les boutons "Top du jour/hier/semaine"</span>
                                        </div>
                                        <div class="columns large-4">
                                            <input type="radio" <?php if(useTopButtons()) {echo ' checked="checked" ';}?> name="checkbox-use-top-buttons" class="checkbox-use-top-buttons no-margin" value="oui"/>oui
                                            <input type="radio" <?php if(!useTopButtons()) {echo ' checked="checked" ';}?> name="checkbox-use-top-buttons" class="checkbox-use-top-buttons no-margin" value="non"/>non
                                        </div>
                                    </div>
                                    <hr class="no-margin"/>
                                    <div class="row">
                                        <div class="columns large-8">
                                            <span>Afficher un bouton de rafraichissement sur l'article</span>
                                        </div>
                                        <div class="columns large-4">
                                            <input type="radio" <?php if(useRefreshButton()) {echo ' checked="checked" ';}?> name="checkbox-use-refresh-button" class="checkbox-use-refresh-button no-margin" value="oui"/>oui
                                            <input type="radio" <?php if(!useRefreshButton()) {echo ' checked="checked" ';}?> name="checkbox-use-refresh-button" class="checkbox-use-refresh-button no-margin" value="non"/>non
                                        </div>
                                    </div>
                                    <hr class="no-margin"/>
                                    <div class="row">
                                        <div class="columns large-8">
                                            <span>Afficher un lien vers le flux RSS sur la page des flux</span>
                                        </div>
                                        <div class="columns large-4">
                                            <input type="radio" <?php if(displayRssButton()) {echo ' checked="checked" ';}?> name="checkbox-display_rss_button" class="checkbox-display_rss_button no-margin" value="oui"/>oui
                                            <input type="radio" <?php if(!displayRssButton()) {echo ' checked="checked" ';}?> name="checkbox-display_rss_button" class="checkbox-display_rss_button no-margin" value="non"/>non
                                        </div>
                                    </div>
                                    <hr class="no-margin"/>
                                    <div class="row">
                                        <div class="columns large-8">
                                            <span>S'inscrire automatiquement aux nouveaux shaarlistes</span>
                                        </div>
                                        <div class="columns large-4">
                                            <input type="radio" <?php if(isInscriptionAuto()) {echo ' checked="checked" ';}?> name="checkbox-inscription_auto" class="checkbox-inscription_auto no-margin" value="oui"/>oui
                                            <input type="radio" <?php if(!isInscriptionAuto()) {echo ' checked="checked" ';}?> name="checkbox-inscription_auto" class="checkbox-inscription_auto no-margin" value="non"/>non
                                        </div>
                                    </div>
                                    <hr class="no-margin"/>
                                    <br/>
                                    <div class="row">
                                        <div class="column large-12">
                                            <h5>Options d'affichage</h5>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="columns large-8">
                                            <span>Rendre la barre de menu fixe (rafraichir la page)</span>
                                        </div>
                                        <div class="columns large-4">
                                            <input type="radio" <?php if(isMenuLocked()) {echo ' checked="checked" ';}?> name="checkbox-lock-menu" class="checkbox-lock-menu no-margin" value="lock"/>oui
                                            <input type="radio" <?php if(!isMenuLocked()) {echo ' checked="checked" ';}?> name="checkbox-lock-menu" class="checkbox-lock-menu no-margin" value="open"/>non
                                        </div>
                                    </div>
                                    <hr class="no-margin"/>
                                    <div class="row">
                                        <div class="columns large-8">
                                                <span>Compacter les articles longs</span>
                                        </div>
                                        <div class="columns large-4">
                                                <input type="radio" <?php if(isExtended()) {echo ' checked="checked" ';}?> name="checkbox-extend" class="checkbox-extend no-margin" value="oui"/>oui
                                                <input type="radio" <?php if(!isExtended()) {echo ' checked="checked" ';}?> name="checkbox-extend" class="checkbox-extend no-margin" value="non"/>non
                                        </div>
                                    </div>
                                    <hr class="no-margin"/>
                                    <div class="row">
                                        <div class="columns large-8">
                                            <span>Activer le scroll infini</span>
                                        </div>
                                        <div class="columns large-4">
                                            <input type="radio" <?php if(useScrollInfini()) {echo ' checked="checked" ';}?> name="checkbox-use_scroll_infini" class="checkbox-use_scroll_infini no-margin" value="oui"/>oui
                                            <input type="radio" <?php if(!useScrollInfini()) {echo ' checked="checked" ';}?> name="checkbox-use_scroll_infini" class="checkbox-use_scroll_infini no-margin" value="non"/>non
                                        </div>
                                    </div>
                                    <hr class="no-margin"/>
                                    <div class="row">
                                        <div class="columns large-8">
                                            <span>Afficher les trucs du style Tipeee</span>
                                        </div>
                                        <div class="columns large-4">
                                            <input type="radio" <?php if(useTipeee()) {echo ' checked="checked" ';}?> name="checkbox-use_tipeee" class="checkbox-use_tipeee no-margin" value="oui"/>oui
                                            <input type="radio" <?php if(!useTipeee()) {echo ' checked="checked" ';}?> name="checkbox-use_tipeee" class="checkbox-use_tipeee no-margin" value="non"/>non
                                        </div>
                                    </div>
                                    <hr class="no-margin"/>
                                    <div class="row">
                                        <div class="columns large-8">
                                            <span>Afficher les images/avatars <span class="button microscopic alert">NEW</span></span>
                                        </div>
                                        <div class="columns large-4">
                                            <input type="radio" <?php if(displayImages()) {echo ' checked="checked" ';}?> name="checkbox-display_img" class="checkbox-display_img no-margin" value="oui"/>oui
                                            <input type="radio" <?php if(!displayImages()) {echo ' checked="checked" ';}?> name="checkbox-display_img" class="checkbox-display_img no-margin" value="non"/>non
                                        </div>
                                    </div>
                                    <hr class="no-margin"/>
                                    <div class="row">
                                        <div class="columns large-8">
                                            <span>Afficher uniquement les liens non visités <span class="button microscopic alert">NEW</span></span>
                                        </div>
                                        <div class="columns large-4">
                                            <input type="radio" <?php if(displayOnlyUnreadArticles()) {echo ' checked="checked" ';}?> name="checkbox-display_only_unread" class="checkbox-display_only_unread no-margin" value="oui"/>oui
                                            <input type="radio" <?php if(!displayOnlyUnreadArticles()) {echo ' checked="checked" ';}?> name="checkbox-display_only_unread" class="checkbox-display_only_unread no-margin" value="non"/>non
                                        </div>
                                    </div>
                                    <hr class="no-margin"/>
                                    
                                    <br/>
                                    <div class="row">
                                        <div class="column large-12">
                                            <h5>Options mainstream</h5>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="columns large-8">
                                            <span>Utiliser <a href="https://github.com/tholman/elevator.js">elevator.js</a> dans la page des flux</span>
                                        </div>
                                        <div class="columns large-4">
                                            <input type="radio" <?php if(useElevator()) {echo ' checked="checked" ';}?> name="checkbox-use-elevator" class="checkbox-use-elevator no-margin" value="oui"/>oui
                                            <input type="radio" <?php if(!useElevator()) {echo ' checked="checked" ';}?> name="checkbox-use-elevator" class="checkbox-use-elevator no-margin" value="non"/>non
                                        </div>
                                    </div>
                                    <hr class="no-margin"/>
                                    <div class="row">
                                        <div class="columns large-8">
                                            <p class="no-dotsies">Utiliser <a class="no-dotsies" href="http://dotsies.org/">dotsies</a> (Y'en a qui ont essayé !)</span>
                                        </div>
                                        <div class="columns large-4">
                                            <input type="radio" <?php if(useDotsies()) {echo ' checked="checked" ';}?> name="checkbox-use-dotsies" class="checkbox-use-dotsies no-margin" value="oui"/>oui
                                            <input type="radio" <?php if(!useDotsies()) {echo ' checked="checked" ';}?> name="checkbox-use-dotsies" class="checkbox-use-dotsies no-margin" value="non"/><span class="no-dotsies">non</span>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="columns large-12 text-right">
                                            <span><form><input type="submit" class="button" value="Enregistrer"></form></span>
                                        </div>
                                    </div>
                                        <!--
                                        <div class="row">
                                            <div class="columns large-8">
                                                <span>En travaux : Activer le mode river (dégroupe les liens)</span>
                                            </div>
                                            <div class="columns large-4">
                                                <input type="radio" <?php if(isModeRiver()) {echo ' checked="checked" ';}?> name="checkbox-mode_river" class="checkbox-mode_river" value="oui"/>oui
                                                <input type="radio" <?php if(!isModeRiver()) {echo ' checked="checked" ';}?> name="checkbox-mode_river" class="checkbox-mode_river" value="non"/>non
                                            </div>
                                        </div>
                                        -->
                                </div>
                            </div>
                        </div>
                        <?php } ?>


                    <?php if (isSerieux() && !$params['creation']) { ?>
                    <div class="row bloc-show" id="report">
                        <div class="columns large-12 center">
                            <div class="panel">
                                <div class="row">
                                    <div class="columns large-12">
                                        <h3>Reporter un bug/commentaire/conseil</h3>
                                        <br/>
                                        <p>Utilisez ce champ si besoin pour toute remarque :</p>
                                        <form method="POST">
                                                <textarea name="message" rows="4"></textarea>
                                                <input type="hidden" name="action" value="report" />
                                                <input class="button" type="submit" value="Envoyer" />
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php } ?>

                    <?php if (isSerieux() && !$params['creation']) { ?>
                    <div id="filtres" class="bloc-show">
                        <div class="row">
                            <div class="columns large-12 center">
                                <div class="panel">
                                    <div class="row">
                                        <div class="columns large-12">
                                            <h3>Filtre sur les tags</h3>
                                            
                                            <form method="POST">
                                                <p>Vous pouvez afficher les articles qui contiennent UNIQUEMENT ces tags :</p>
                                                <?php 
                                                if (!empty(getTags())) {
                                                    ?><textarea name="tags" rows="4" placeholder="végétarisme, fun..."><?php echo htmlentities(implode(' ', getTags()));?></textarea><?php
                                                } else {
                                                    ?><textarea name="tags" rows="4" placeholder="végétarisme, fun..."></textarea><?php
                                                }
                                                ?>
                                                <p>Vous pouvez filtrer les articles qui ne vous intéressent pas en fonction de leurs tags</p>
                                                <textarea name="not_allowed_tags" rows="4" placeholder="iphone, politique, science, html, css, video, wtf..."><?php echo htmlentities(implode(' ', getNotAllowedTags()));?> </textarea>
                                                <input type="hidden" name="action" value="enregistrer_tags" />
                                                
                                                <input class="button" type="submit" value="Sauvegarder" />
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="columns large-12 center">
                                <div class="panel">
                                    <div class="row">
                                        <div class="columns large-12">
                                            <h3>Filtre sur les urls</h3>
                                            <form method="POST">
                                                <p>Ne jamais afficher de lien vers les sites web suivants :</p>
                                                <textarea name="not_allowed_urls" rows="4" placeholder="youtube.com..."><?php echo htmlentities(implode(' ', getNotAllowedUrls()));?> </textarea>
                                                <input type="hidden" name="action" value="enregistrer_urls" />
                                                <input class="button" type="submit" value="Sauvegarder" />
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php } ?>

                    <div class="row bloc-show" id="pwd" style="display:none;">
                        <div class="columns large-12 center">
                            <div class="panel">
                                <div class="row">
                                    <div class="columns large-12">
                                        <h3>Protection par mot de passe <span class="button tiny alert">NEW</span></h3>
                                        <?php if (isPassword()) { ?>
                                            <span>Ce compte est actuellement protégé par un mot de passe.</span>
                                        <?php } ?>
                                        <form method="POST" action="?">
                                            <div class="row">
                                                <div class="columns large-12">
                                                    <input type="hidden" name="action" value="enregistrer_password"/>
                                                    <input autocomplete="off" placeholder="Mot de passe" type="password" name="password" value="" />
                                                    <input class="button" type="submit" value="Enregistrer" />
                                                </div>
                                            </div>
                                        </form>
                                        <p>La protection du compte permettra d'accéder à des fonctions avancées comme : 
                                            <ul>
                                                <li>Modification de l'url de son shaarli</li>
                                                <li>Suppression du shaarli de la page d'abonnement</li>
                                                <li>Modification de son avatar</li>
                                                <li>Messagerie non accessible de l'extérieur (même si c'est assez virtuel)</li>
                                                <li>Affichage du profil aux autres profils (avec des statistiques par exemple)</li>
                                            </ul>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if (!$params['pas_de_profil'] && !$params['creation'] && !isShaarliste()) { ?>
                        <div class="row bloc-show" id="mon-shaarli">
                            <div class="columns large-12 center">
                                <div class="panel">
                                    <div class="row">
                                        <div class="columns large-12">
                                            <h3>Url de mon shaarli</h3>
                                            <p>Permet de reshaarlier les liens</p>
                                            <form method="POST">
                                                <input name="shaarli_url" type="text" value="<?php echo htmlentities($params['shaarli_url']); ?>" placeholder="http://example.com/shaarli/"/>
                                                <input type="hidden" name="action" value="mon_shaarli" />
                                                <input type="hidden" name="action" value="mon_shaarli" />
                                                <?php
                                                if (!$params['creation']) {
                                                ?>
                                                 <div class="row">
                                                    <div class="columns large-12">
                                                        <input class="button" type="submit" value="Sauvegarder" />
                                                    </div>
                                                </div>
                                                <?php
                                                }
                                                ?>
                                            </form>
                                            <p class="astuce">Note : votre shaarli apparaitra dans la page des abonnements après modération</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                    <?php if (isShaarliste()) { ?>
                        <div class="row bloc-show" id="modifier-shaarli">
                            <div class="columns large-12 center">
                                <div class="panel">
                                    <div class="row">
                                        <div class="columns large-12">
                                            <h3>Modifier mon shaarli</h3>
                                        </div>
                                    </div>
                                    <br/>
                                    <div class="row">
                                        <div class="columns large-8">
                                            <span>Apparaitre dans la liste des abonnements</span>
                                        </div>
                                        <div class="columns large-4">
                                            <input type="radio" <?php if(isOnAbonnements()) {echo ' checked="checked" ';}?> name="checkbox-on_abonnements" class="checkbox-on_abonnements no-margin" value="oui"/>oui
                                            <input type="radio" <?php if(!isOnAbonnements()) {echo ' checked="checked" ';}?> name="checkbox-on_abonnements" class="checkbox-on_abonnements no-margin" value="non"/>non
                                        </div>
                                    </div>
                                    <hr class="no-margin"/>
                                    <div class="row">
                                        <div class="columns large-8">
                                            <span>Apparaitre dans la River</span>
                                        </div>
                                        <div class="columns large-4">
                                            <input type="radio" <?php if(isOnRiver()) {echo ' checked="checked" ';}?> name="checkbox-on_river" class="checkbox-on_river no-margin" value="oui"/>oui
                                            <input type="radio" <?php if(!isOnRiver()) {echo ' checked="checked" ';}?> name="checkbox-on_river" class="checkbox-on_river no-margin" value="non"/>non
                                        </div>
                                    </div>
                                    
                                    <br/>
                                    
                                    <div class="row">
                                        <div class="columns large-12">
                                            <h4>Modifier l'url de mon shaarli</h4>
                                        </div>
                                        <div class="columns large-12">
                                            <form method="POST">
                                                <input name="shaarli_url" type="text" value="<?php echo htmlentities($params['shaarli_url']); ?>" placeholder="http://example.com/shaarli/"/>
                                                <input type="hidden" name="action" value="mon_shaarli" />
                                                <input type="hidden" name="action" value="mon_shaarli" />
                                                <?php
                                                if (!$params['creation']) {
                                                ?>
                                                 <div class="row">
                                                    <div class="columns large-12">
                                                        <input class="button" type="submit" value="Sauvegarder" />
                                                    </div>
                                                </div>
                                                <?php
                                                }
                                                ?>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                    
                    
                    <?php if ($params['creation']) { ?>
                        <div class="row">
                            <div class="column large-12 text-center">
                                <h1>Créer un profil</h1>
                            </div>
                        </div>
                        <hr/>
                        <form method="POST" action="?">
                            <div class="row">
                                <div class="column large-12 text-center">
                                    <h2>Sélectionnez les shaarlistes que vous souhaitez suivre</h2>
                                </div>
                            </div>
                            <?php
                            $this->renderListeShaarlistes($params);
                            ?>
                            <div class="row">
                                <div class="columns large-6 large-centered">
                                    <div class="panel">
                                        <div class="row">
                                            <div class="columns large-12 text-center">
                                                <h3>C'est presque fini !</h3>
                                                <p>Choisissez un nom de profil</p>
                                                <input type="hidden" name="action" value="enregistrer"/>
                                                <input name="profil_id" type="text" value="<?php echo htmlentities(getUtilisateurId());?>"/>

                                                <label>Facultatif : un mot de passe</label>
                                                <input placeholder="Mot de passe" type="password" name="password" value="" />
                                                <input disabled="disabled" id="input-enregistrer-profil" class="button success" type="submit" value="Enregistrer mon profil" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    <?php } ?>
                <?php } ?>
                
                <?php
                $this->renderScript($params);
                ?>
                    </body>
                </html>

                <?php
        }
    public static function renderScript($params)
    {
        ?>
        <script>
            $('.checkbox-lock-menu').click(function() {
                addOption($(this), 'lock', $(this).val());
            });

            $('.checkbox-display-shaarlistes-non-suivis').click(function() {
                addOption($(this), 'display_shaarlistes_non_suivis', $(this).val());
            });
            $('.checkbox-display-empty-description').click(function() {
                addOption($(this), 'display_empty_description', $(this).val());
            });
            $('.checkbox-display_bloc_conversation').click(function() {
                addOption($(this), 'display_bloc_conversation', $(this).val());
            });
            $('.checkbox-display_only_new_articles').click(function() {
                addOption($(this), 'display_only_new_articles', $(this).val());
            });
            $('.checkbox-display-best-article').click(function() {
                addOption($(this), 'display_best_article', $(this).val());
            });
            $('.checkbox-inscription_auto').click(function() {
                addOption($(this), 'inscription_auto', $(this).val());
            });
            $('.checkbox-use_scroll_infini').click(function() {
                addOption($(this), 'use_scroll_infini', $(this).val());
            });
            $('.checkbox-extend').click(function() {
                addOption($(this), 'extend', $(this).val());
            });
            $('.checkbox-mode_river').click(function() {
                addOption($(this), 'mode_river', $(this).val());
            });
            $('.checkbox-use-elevator').click(function() {
                addOption($(this), 'use_elevator', $(this).val());
            });
            $('.checkbox-use-useless-options').click(function() {
                addOption($(this), 'use_useless_options', $(this).val());
            });
            $('.checkbox-use-dotsies').click(function() {
                addOption($(this), 'use_dotsies', $(this).val());
            });
            $('.checkbox-use-top-buttons').click(function() {
                addOption($(this), 'use_top_buttons', $(this).val());
            });
            $('.checkbox-use-refresh-button').click(function() {
                addOption($(this), 'use_refresh_button', $(this).val());
            });
            $('.checkbox-display_rss_button').click(function() {
                addOption($(this), 'display_rss_button', $(this).val());
            });
            $('.checkbox-use_tipeee').click(function() {
                addOption($(this), 'use_tipeee', $(this).val());
            });
            $('.checkbox-display_img').click(function() {
                addOption($(this), 'display_img', $(this).val());
            });
            $('.checkbox-display_only_unread').click(function() {
                addOption($(this), 'display_only_unread', $(this).val());
            });
            $('.checkbox-on_abonnements').click(function() {
                addOption($(this), 'on_abonnements', $(this).val());
            });
            $('.checkbox-on_river').click(function() {
                addOption($(this), 'on_river', $(this).val());
            });
            function addAbo(that, id, action) {
                var r = new XMLHttpRequest(); 
                var params = "do="+action+"&id=" + id;
                r.open("POST", "add.php", true); 
                r.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                r.onreadystatechange = function () {
                    that.attr('data-waiting', 'false');
                    if (r.readyState == 4) {
                        if(r.status == 200){
                            var checkboxId = '#' + that.attr('data-shaarliste-id');
                            if(action == 'add') {
                                $('#a-voir-river').removeClass('hidden');
                                $('#input-enregistrer-profil').prop('disabled', false);
                                that.addClass('selected');
                                $(checkboxId).prop('checked', true);
                                $('#span-nbabonnements').text(parseInt($('#span-nbabonnements').text()) + 1);
                            }else {
                                $('#span-nbabonnements').text(parseInt($('#span-nbabonnements').text()) - 1);
                                $(checkboxId).prop('checked', false);
                                that.removeClass('selected');
                            }
                            return; 
                        }
                        else {
                            that.text = '-Erreur-';
                            return; 
                        }
                    }
                }; 
                r.send(params);
            }
            $('.shaarliste-selection').click(function() {
                var checkboxId = '#' + $(this).attr('data-shaarliste-id');
                
                if ($(this).attr('data-waiting') != 'true') {
                    $(this).attr('data-waiting', 'true');
                    if ($(checkboxId).is(':checked')) {
                        addAbo($(this), $(this).attr('data-shaarliste-id'), 'delete');
                    } else {
                        addAbo($(this), $(this).attr('data-shaarliste-id'), 'add');
                    }
                }
            });


            $('#button-synchro-favicon').click(function() {
                var r = new XMLHttpRequest(); 
                var params = "";
                $('#img-synchro-favicon').show();
                r.open("POST", "synchro_favicon.php", true); 
                r.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                r.onreadystatechange = function () {
                    $('#img-synchro-favicon').hide();
                    if (r.readyState == 4) {
                        if(r.status == 200){
                            return; 
                        }
                        else {
                            return; 
                        }
                    }
                }; 
                r.send(params);
            });

            $('#button-synchro-shaarli').click(function() {
                var r = new XMLHttpRequest(); 
                var params = "";
                $('#img-synchro-shaarli').show();
                r.open("POST", "synchro_shaarli.php", true); 
                r.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                r.onreadystatechange = function () {
                    $('#img-synchro-shaarli').hide();
                    if (r.readyState == 4) {
                        if(r.status == 200){
                            return; 
                        }
                        else {
                            return; 
                        }
                    }
                }; 
                r.send(params);
            });
            
            $('#button-tous').click(function() {
                var checkbox = $('.checkbox-shaarliste');
                $.each( checkbox, function( key, value ) {
                    $(value).prop('checked', true);
                    var shaarlisteSelection = '#shaarliste-selection-' + $(value).attr('id');
                    $(shaarlisteSelection).addClass('selected');
                });
                <?php
                if (!$params['creation']) {
                ?>
                    $('#form-abonnements').submit();
                <?php
                } else {
                ?>
                    /* On va directement en fin de page */
                    $("html, body").animate({ scrollTop: $(document).height()-$(window).height() });
                <?php }?>
                
                $('#input-enregistrer-profil').prop('disabled', false);
            });

            $('#button-personne').click(function() {
                var checkbox = $('.checkbox-shaarliste');
                $.each( checkbox, function( key, value ) {
                    $(value).prop('checked', false);
                    var shaarlisteSelection = '#shaarliste-selection-' + $(value).attr('id');
                    $(shaarlisteSelection).removeClass('selected');
                });
                $('#form-abonnements').submit();
            });
            $(document).foundation();

            function blocShow() {
                console.log(location.hash);
                if ('#pwd' == location.hash) {
                    $(".bloc-show").hide();
                    $("#pwd").show();
                }
                if ('#options' == location.hash) {
                    $(".bloc-show").hide();
                    $("#options").show();
                }
                if ('#report' == location.hash) {
                    $(".bloc-show").hide();
                    $("#report").show();
                }
                if ('#filtres' == location.hash) {
                    $(".bloc-show").hide();
                    $("#filtres").show();
                }
                if ('#modifier-shaarli' == location.hash) {
                    $(".bloc-show").hide();
                    $("#modifier-shaarli").show();
                }
                if ('#mon-shaarli' == location.hash) {
                    $(".bloc-show").hide();
                    $("#mon-shaarli").show();
                }
            }
            
            $('.link-show').click(function(event) {
                $(".bloc-show").hide();
                $($(this).attr('href')).show();
                event.preventDefault();
            });
            
            $(".bloc-show").hide();
            blocShow();

        </script>
        <?php
    }
}

$dashboard = new Dashboard();
$dashboard->run();



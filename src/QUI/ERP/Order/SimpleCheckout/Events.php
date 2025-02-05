<?php

namespace QUI\ERP\Order\SimpleCheckout;

use phpseclib3\File\ASN1\Maps\NameConstraints;
use QUI;
use QUI\Exception;
use QUI\Projects\Site\Edit;

use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

class Events
{
    public static function siteCreateChildEnd(Edit $Site): void
    {
        if ($Site->getAttribute('type') !== 'quiqqer/order-simple-checkout:types/productLandingPage') {
            return;
        }

        QUI\Permissions\Permission::setUser(
            QUI::getUsers()->getSystemUser()
        );

        $BrickManager = QUI\Bricks\Manager::init();
        $Project = $Site->getProject();

        foreach (self::getDemoBricksData($Site) as $data) {
            $brickDataToSave = [
                'attributes' => $data['attributes']
            ];

            if (isset($data['settings'])) {
                $brickDataToSave['settings'] = $data['settings'];
            }

            $Brick = new QUI\Bricks\Brick($brickDataToSave);

            $brickId = $BrickManager->createBrickForProject($Project, $Brick);

            // First, set brick type. so, extra settings and attributes can be set
            if (!empty($brickDataToSave['attributes']['type'])) {
                $BrickManager->saveBrick($brickId, [
                    'attributes' => [
                        'title' => $brickDataToSave['attributes']['title'],
                        'type' => $brickDataToSave['attributes']['type']
                    ]
                ]);
            }

            // Assign the data here first
            $BrickManager->saveBrick($brickId, $brickDataToSave);

            // add brick to site
            self::addBrickToSite($Site, $brickId, $data['assignedBrickArea']);
        }

        // save site
        $Site->save(QUI::getUsers()->getSystemUser());
    }

    // region helper methods

    /**
     * Add a brick to a site.
     *
     * @param QUI\Projects\Site\Edit $Site Site object
     * @param string|int $brickId Brick ID
     * @param string $areaName Name of the area
     *
     * @return void
     */
    public static function addBrickToSite(
        Edit $Site,
        string|int $brickId,
        string $areaName
    ): void {
        $areas = $Site->getAttribute('quiqqer.bricks.areas');

        if (is_string($areas)) {
            $areas = json_decode($areas, true);
        }

        if (!is_array($areas)) {
            $areas = [];
        }

        $areas[$areaName][] = [
            'brickId' => $brickId,
            'customFields' => '',
            'uid' => ''
        ];

        $Site->setAttribute('quiqqer.bricks.areas', json_encode($areas));
    }

    /**
     * @throws Exception
     */
    public static function getDemoBricksData(Edit $Site): array
    {
        $Project = $Site->getProject();
        $Media = $Project->getMedia();
        $PlaceholdersImage = $Media->getPlaceholderImage();

        if (!$PlaceholdersImage) {
            try {
                $Media = $Project->getMedia();
                $imageIds = $Media->getChildrenIds([
                    'limit' => 1,
                    'where' => [
                        'type' => 'image',
                        'active' => 1,
                        'deleted' => 0
                    ]
                ]);

                if (isset($imageIds[0])) {
                    $Image = $Media->get($imageIds[0]);
                } else {
                    $RootFolder = $Media->firstChild();
                    $Image = $RootFolder->uploadFile(
                        OPT_DIR . 'quiqqer/order-simple-checkout/bin/images/demo/placeholder-image-vertical-grey.png'
                    );
                    $Image->activate();
                }

                $Config = QUI::getConfig('etc/projects.ini.php');
                $Config->setValue($Project->getName(), 'placeholder', $Image->getUrl());
                $Config->save();
                $PlaceholdersImage = $Image;
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        $placeholderImage = $PlaceholdersImage->getUrl();

        return [
            [
                'attributes' => [
                    'type' => '\\QUI\\PresentationBricks\\Controls\\WallpaperTextArrow',
                    'title' => 'Produkt Landingpage - Header (mit Produktbild) - ' . QUI\Utils\Uuid::get(),
                    'description' => '(Generierter Baustein für Seite ' . $Site->getId() . ')',
                    'content' => "<h1>Produkt Landing Page<br />\nf&uuml;r einen besonderer Artikel</h1>\n\n<p>Phasellus tempus. Integer tincidunt. Nam eget dui.. Praesent metus tellus, elementum eu, semper a, adipiscing nec, purus.</p>\n\n<p>&nbsp;</p>\n\n<p><a class=\"btn btn-primary btn-large btn-lg\" data-qui-productlandingpage-cta=\"1\" href=\"#\">Jetzt bestellen <i class=\"fa-solid fa-cart-shopping\"></i></a></p>\n",
                    'areas' => 'headerSuffix'
                ],
                'settings' => [
                    'classes' => 'demo__product-landing-page__brick__header',
                    'image' => $placeholderImage,
                    'general.fullWidth' => 1,
                    'general.noSpacing' => 1
                ],
                'assignedBrickArea' => 'headerSuffix'
            ],
            [
                'attributes' => [
                    'type' => '\\QUI\\Gallery\\Controls\\Logo\\InfiniteCarousel',
                    'title' => 'Produkt Landingpage - Logo-Karussell - ' . QUI\Utils\Uuid::get(),
                    'description' => '(Generierter Baustein für Seite ' . $Site->getId() . ')',
                    'content' => "",
                    'areas' => 'headerSuffix'
                ],
                'settings' => [
                    'classes' => 'demo__product-landing-page__brick__logo',
                    'folderId' => '1',
                    'imgHeight' => '50',
                    'carouselBlockSpacing' => 'normal',
                    'animationDuration' => '40',
                    'stopAnimationOnHover' => '1',
                    'general.fullWidth' => 1,
                    'general.noSpacing' => 1
                ],
                'assignedBrickArea' => 'headerSuffix'
            ],
            [
                'attributes' => [
                    'type' => '\\QUI\\Bricks\\Controls\\BoxContentAdvanced',
                    'title' => 'Produkt Landingpage - Boxen mit Icon - ' . QUI\Utils\Uuid::get(),
                    'description' => '(Generierter Baustein für Seite ' . $Site->getId() . ')',
                    'content' => "",
                    'areas' => 'headerSuffix'
                ],
                'settings' => [
                    'classes' => 'demo__product-landing-page__brick__boxes',
                    'template' => 'standard',
                    'centerText' => true,
                    'entriesPerLine' => 4,
                    'entries' => "[{\"entryTitle\":\"Ut non enim eleifend\",\"entrySubTitle\":\"\",\"entryImage\":\"fa fa-anchor-circle-check\",\"entryUrl\":\"\",\"entryOrder\":\"\",\"entryContent\":\"<p>Curabitur turpis. Maecenas nec odio et ante tincidunt tempus.</p>\"},{\"entryTitle\":\"Nam ipsum risus\",\"entrySubTitle\":\"\",\"entryImage\":\"fa fa-bar-chart\",\"entryUrl\":\"\",\"entryOrder\":\"\",\"entryContent\":\"<p>Sed hendrerit. Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem.</p>\"},{\"entryTitle\":\"Vestibulum fringilla pede\",\"entrySubTitle\":\"\",\"entryImage\":\"fa fa-arrow-trend-up\",\"entryUrl\":\"\",\"entryOrder\":\"\",\"entryContent\":\"<p>In turpis. Praesent nec nisl a purus blandit viverra.</p>\"},{\"entryTitle\":\"Fusce risus nisl\",\"entrySubTitle\":\"\",\"entryImage\":\"fa fa-envelopes-bulk\",\"entryUrl\":\"\",\"entryOrder\":\"\",\"entryContent\":\"<p>Phasellus viverra nulla ut metus varius laoreet.</p>\"}]"
                ],
                'assignedBrickArea' => 'headerSuffix'
            ],
            [
                'attributes' => [
                    'type' => '\\QUI\\Bricks\\Controls\\TextAndImageMultiple',
                    'title' => 'Produkt Landingpage - Features (Text und Bilder) - ' . QUI\Utils\Uuid::get(),
                    'description' => '(Generierter Baustein für Seite ' . $Site->getId() . ')',
                    'content' => "<h2 style=\"text-align: center;\">In consectetuer turpis ut velit</h2>\n\n<p style=\"max-width: 60ch; text-align: center; margin-inline: auto;\">Morbi nec metus. Quisque id mi. Phasellus leo dolor, tempus non, auctor et, hendrerit quis, nisi. Praesent nec nisl a purus blandit viverra. Nulla consequat massa quis enim.</p>\n",
                    'areas' => 'headerSuffix'
                ],
                'settings' => [
                    'textPosition' => 'center',
                    'imagePosition' => 'imageRightAlternately',
                    'textRatio' => '50',
                    'maxImageWidth' => '500',
                    'entriesPerLine' => 4,
                    'entries' => "[{\"image\":\"" . $placeholderImage . "\",\"text\":\"<h3>Phasellus viverra nulla ut metus</h3><p>Duis leo. Aliquam lorem ante, dapibus in, viverra quis, feugiat a, tellus. Donec mi odio, faucibus at, scelerisque quis, convallis in, nisi. Sed in libero ut nibh placerat accumsan. Phasellus volutpat, metus eget egestas mollis, lacus lacus blandit dui, id egestas quam mauris ut lacus.</p>\",\"isDisabled\":0},{\"image\":\"" . $placeholderImage . "\",\"text\":\"<h3>Duis vel nibh at velit</h3><p>In auctor lobortis lacus. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Cras ultricies mi eu turpis hendrerit fringilla.&nbsp;</p><ul><li>Ut non enim eleifend felis pretium feugiat.</li><li>Fusce vulputate eleifend sapien.</li><li>Fusce neque.</li><li>Quisque id odio.</li>t<li>Curabitur suscipit suscipit tellus.</li></ul><p><a class=\\\"btn btn-primary btn-large btn-lg\\\" href=\\\"#\\\" data-qui-productlandingpage-cta=\\\"1\\\">Jetzt bestellen <i class=\\\"fa-solid fa-cart-shopping\\\"></i></a></p>\",\"isDisabled\":0}]"
                ],
                'assignedBrickArea' => 'headerSuffix'
            ],
            [
                'attributes' => [
                    'type' => '\\QUI\\PresentationBricks\\Controls\\WallpaperText',
                    'title' => 'Produkt Landingpage - Slogan - ' . QUI\Utils\Uuid::get(),
                    'description' => '(Generierter Baustein für Seite ' . $Site->getId() . ')',
                    'content' => "<p style=\"text-align: center;\"><span style=\"font-size:22px;\"><em>Phasellus viverra nulla ut metus varius laoreet. Aenean massa. Quisque libero metus, condimentum nec.</em></span></p>\n",
                    'areas' => 'headerSuffix'
                ],
                'settings' => [
                    'minHeight' => '400px',
                    "bg-color" => "#f5f5f5",
                    "content-position" => "center",
                    'contentMaxWidth' => '600',
                    'fontColor' => '',
                    'general.fullWidth' => 1,
                    'general.noSpacing' => 1
                ],
                'assignedBrickArea' => 'headerSuffix'
            ],
            [
                'attributes' => [
                    'type' => '\\QUI\\Bricks\\Controls\\Slider\\CustomerReviewsSlider',
                    'title' => 'Produkt Landingpage - Kunden Rezensionen - ' . QUI\Utils\Uuid::get(),
                    'description' => '(Generierter Baustein für Seite ' . $Site->getId() . ')',
                    'content' => "",
                    'areas' => 'headerSuffix,footerPrefix'
                ],
                'settings' => [
                    'template' => 'templateOne',
                    'perView' => 3,
                    'gap' => 40,
                    'autoplay' => true,
                    'delay' => 5000,
                    'showArrows' => false,
                    'sliderHeight' => 'fixed',
                    'entries' => "[{\"image\":\"" . $placeholderImage . "\",\"customerName\":\"John Doe\",\"addition\":\"\",\"url\":\"\",\"urlTitle\":\"\",\"review\":\"<p>Vivamus laoreet. Praesent blandit laoreet nibh. Maecenas malesuada. Morbi vestibulum volutpat enim. Pellentesque dapibus hendrerit tortor.</p>\",\"isDisabled\":0},{\"image\":\"" . $placeholderImage . "\",\"customerName\":\"Peter Young\",\"addition\":\"\",\"url\":\"\",\"urlTitle\":\"\",\"review\":\"<p>Vestibulum suscipit nulla quis orci. Phasellus nec sem in justo pellentesque facilisis. Duis leo. Curabitur ligula sapien, tincidunt non, euismod vitae, posuere imperdiet, leo. Vestibulum rutrum, mi nec elementum vehicula.</p>\",\"isDisabled\":0},{\"image\":\"" . $placeholderImage . "\",\"customerName\":\"Ann Parks\",\"addition\":\"\",\"url\":\"\",\"urlTitle\":\"\",\"review\":\"<p>Ut leo. Etiam rhoncus. Ut id nisl quis enim dignissim sagittis. Nullam accumsan lorem in dui.</p>\",\"isDisabled\":0},{\"image\":\"" . $placeholderImage . "\",\"customerName\":\"Katrin Strong\",\"addition\":\"\",\"url\":\"\",\"urlTitle\":\"\",\"review\":\"<p>Vestibulum ullamcorper mauris at ligula. Proin faucibus arcu quis ante. Quisque libero metus, condimentum nec, tempor a, commodo mollis, magna. Cras ultricies mi eu turpis hendrerit fringilla. Sed fringilla mauris sit amet nibh.</p>\",\"isDisabled\":0}]"
                ],
                'assignedBrickArea' => 'headerSuffix'
            ]
        ];
    }

    //endregion
}

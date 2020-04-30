<?php
/*
Plugin Name: Black Pug LTD RTK Integration
Version: 4.2.8
Description: RTK jita.js integration plugin
Author: Black Pug LTD
*/

if (!defined('BPLTD_RTK_ASSET_VER')) {
    define('BPLTD_RTK_ASSET_VER', '4.2.8');
}

if (!defined('BPLTD_RTK_JS_DIR')) {
    define('BPLTD_RTK_JS_DIR', plugins_url('/js/', __FILE__));
}

if (!defined('BPLTD_RTK_CSS_DIR')) {
    define('BPLTD_RTK_CSS_DIR', plugins_url('/css/', __FILE__));
}

if (!class_exists('BPLTD_RTK_Integration')) {
    class BPLTD_RTK_Integration
    {
        private $settings;
        private $source;

        // mobile markers
        private $topLoaded = false;
        private $middleLoaded = false;
        private $bottomLoaded = false;

        // desktop markers
        private $desktopLoaded = false;

        // legacy
        private $mobileLoaded = false;

        public function __construct()
        {
            add_action('admin_menu', array($this, 'blackpug_settings_page'));

            add_action('wp_enqueue_scripts', array($this, 'bpltd_rtk_add_script'));
            add_action('bpltd_rtk_script', array($this, 'bpltd_rtk_add_scripts'));
            add_shortcode('rtk_adunit', array($this, 'bpltd_rtk_adunit_shortcode'));
            add_shortcode('adunit', array($this, 'bpltd_rtk_adunit_shortcode'));

            add_shortcode('rtk_adunit_top', array($this, 'bpltd_rtk_adunit_top_shortcode'));
            add_shortcode('rtk_adunit_middle', array($this, 'bpltd_rtk_adunit_middle_shortcode'));
            add_shortcode('rtk_adunit_bottom', array($this, 'bpltd_rtk_adunit_bottom_shortcode'));
            add_shortcode('rtk_adunit_end', array($this, 'bpltd_rtk_adunit_end_shortcode'));

            /**
             * Ignore the <!--nextpage--> for content pagination.
             *
             * @see http://wordpress.stackexchange.com/a/183587/26350
             */

            add_action( 'the_post', function( $post )
            {
                if ( false !== strpos( $post->post_content, '<!--nextpage-->' ) )
                {
                    // Reset the global $pages:
                    $GLOBALS['pages']     = [ $post->post_content ];
                    // Reset the global $numpages:
                    $GLOBALS['numpages']  = 0;
                    // Reset the global $multipage:
                    $GLOBALS['multipage'] = false;
                }

            }, 99 );

            add_filter('the_content', function ($content) {
                global $post;

                $content = trim($content);

                if(
                    !wp_is_mobile() &&
                    substr($content,-36) != '[rtk_adunit desktop_last="RTK_I7VB"]' &&
                    substr($content,-16) != '[rtk_adunit_end]' &&
                    $post &&
                    get_post_type() == 'post' &&
                    get_page_template_slug($post->ID) == 'single-onepage.php'
                ) {
                    $html = '</div>
                        <section class="grid-item content-right">
                            <div class="content-sticky">
                                <div class="RTK_3luH rtkadunit"></div>
                            </div>
                        </section>
                    </div>
                    
                    <section class="break automated">
                        <div class="RTK_I7VB rtkadunit">&nbsp;</div>
                    </section>';
                    $content .= $html;
                }

                return $content;
            });

            $this->source = (isset($_GET['utm_source'])) ? $_GET['utm_source'] : null;

            $settings = get_option('bpltd_rtk_integration_settings', []);

            if ($this->source === null) {
                $sources = array_reverse($settings['sources']);
                $this->settings = array_pop($sources);
            } else {
                if (array_key_exists($this->source, $settings['sources'])) {
                    $this->settings = $settings['sources'][$this->source];
                } else {
                    $sources = array_reverse($settings['sources']);
                    $this->settings = array_pop($sources);
                }
            }
        }

        public function blackpug_settings_page() {
            add_options_page(
                'BPLTD RTK Settings',
                'BPLTD RTK Settings',
                'manage_options',
                'bpltd-rtk-settings-page',
                array($this, 'bpltd_rtk_settings_page')
            );
        }

        public function bpltd_rtk_settings_page(  ) {
            if (isset($_POST['bpltd_rtk_integration_settings'])) {
                if (!current_user_can('manage_options')) {
                    wp_die('Unauthorized user');
                }

                $value = $_POST['bpltd_rtk_integration_settings'];
                update_option('bpltd_rtk_integration_settings', $value);
            } else if (isset($_POST['submit'])) {
                update_option('bpltd_rtk_integration_settings', []);
            }

            $settings = get_option('bpltd_rtk_integration_settings', []);

            ?>
            <style>
                form#bpltd-rtk-integration-settings-admin-page legend {
                    color: #23282d;
                    font-size: 1.2em;
                    margin: 1em 0;
                    display: block;
                    font-weight: 600;
                }

                form#bpltd-rtk-integration-settings-admin-page label {
                    width: 200px;
                    display: inline-block;
                }

                form#bpltd-rtk-integration-settings-admin-page input.bpltd-rtk-integration-option {
                    width: 600px;
                    display: inline-block;
                }

                form#bpltd-rtk-integration-settings-admin-page fieldset.bpltd-rtk-integration-option-fieldset {
                    margin-left: 50px;
                    margin-top: 15px;
                }

                form#bpltd-rtk-integration-settings-admin-page fieldset.bpltd-rtk-integration-option-fieldset-outer {
                    max-width: 1024px;
                }

                form#bpltd-rtk-integration-settings-admin-page a.bpltd-rtk-integration-option-delete {
                    display: block;
                    float: right;
                }
            </style>

            <form id="bpltd-rtk-integration-settings-admin-page" method='post'>
                <h2>Black Pug LTD RTK Integration Settings Admin Page</h2>

                <fieldset id="bpltd-rtk-integration-sources">
                    <legend>Sources</legend>

                    <?php
                    if (!isset($settings['sources']) || empty($settings['sources'])) {
                        echo '<p class="bpltd-rtk-integration-no-sources">No sources set.</p>';
                    } else {
                        foreach ($settings['sources'] as $sourceName => $source) {
                            $source['mobile-auction-code'] = (isset($source['mobile-auction-code'])) ? $source['mobile-auction-code'] : '';
                            $source['mobile-adunit-code'] = (isset($source['mobile-adunit-code'])) ? $source['mobile-adunit-code'] : '';
                            $source['mobile-sticky-auction-code'] = (isset($source['mobile-sticky-auction-code'])) ? $source['mobile-sticky-auction-code'] : '';
                            $source['desktop-auction-code'] = (isset($source['desktop-auction-code'])) ? $source['desktop-auction-code'] : '';
                            $source['desktop-adunit-code'] = (isset($source['desktop-adunit-code'])) ? $source['desktop-adunit-code'] : '';
                            $source['quiz-auction-code'] = (isset($source['quiz-auction-code'])) ? $source['quiz-auction-code'] : '';
                            $source['quiz-adunit-code'] = (isset($source['quiz-adunit-code'])) ? $source['quiz-adunit-code'] : '';
                            $source['quiz-sticky-auction-code'] = (isset($source['quiz-sticky-auction-code'])) ? $source['quiz-sticky-auction-code'] : '';

                            $displaySourceName = $sourceName;

                            if($sourceName == 'facebook') {
                                $displaySourceName .= ' (default)';
                            }

                            echo '<fieldset class="bpltd-rtk-integration-option-fieldset bpltd-rtk-integration-option-fieldset-outer">
                                <legend>' . $displaySourceName . '</legend>
                                
                                <fieldset class="bpltd-rtk-integration-option-fieldset">
                                    <legend>Mobile</legend>
                                    <div>
                                        <label for="bpltd-rtk-integration-mobile-' . $sourceName . '-auction-code">Auction Unique Id</label>
                                        <input id="bpltd-rtk-integration-mobile-' . $sourceName . '-auction-code" class="bpltd-rtk-integration-option" name="bpltd_rtk_integration_settings[sources]['.$sourceName.'][mobile-auction-code]" value="' . $source['mobile-auction-code'] . '" placeholder="Unique ID (shortcode) for the auction available from the RTK dashboard" />
                                    </div>
                                    
                                    <div>
                                        <label for="bpltd-rtk-integration-mobile-' . $sourceName . '-adunit-code">Ad-Unit Codes</label>
                                        <input id="bpltd-rtk-integration-mobile-' . $sourceName . '-adunit-code" class="bpltd-rtk-integration-option" name="bpltd_rtk_integration_settings[sources]['.$sourceName.'][mobile-adunit-code]" value="' . $source['mobile-adunit-code'] . '" placeholder="Ad-unit codes available from the RTK dashboard (RTK_dVa8, RTK_wCZW, RTK_z9hm)" />
                                    </div>
                                    
                                    <div>
                                        <label for="bpltd-rtk-integration-mobile-' . $sourceName . '-sticky-adunit-code">Sticky Auction Unique Id</label>
                                        <input id="bpltd-rtk-integration-mobile-' . $sourceName . '-sticky-adunit-code" class="bpltd-rtk-integration-option" name="bpltd_rtk_integration_settings[sources]['.$sourceName.'][mobile-sticky-auction-code]" value="' . $source['mobile-sticky-auction-code'] . '" placeholder="Unique ID (shortcode) for the auction available from the RTK dashboard" />
                                    </div>
                                </fieldset>
                                
                                <fieldset class="bpltd-rtk-integration-option-fieldset">
                                    <legend>Desktop</legend>
                                    <div>
                                        <label for="bpltd-rtk-integration-desktop-' . $sourceName . '-auction-code">Auction Unique Id</label>
                                        <input id="bpltd-rtk-integration-desktop-' . $sourceName . '-auction-code" class="bpltd-rtk-integration-option" name="bpltd_rtk_integration_settings[sources]['.$sourceName.'][desktop-auction-code]" value="' . $source['desktop-auction-code'] . '" placeholder="Unique ID (shortcode) for the auction available from the RTK dashboard" />
                                    </div>
                                    
                                    <div>
                                        <label for="bpltd-rtk-integration-desktop-' . $sourceName . '-adunit-code">Ad-Unit Codes</label>
                                        <input id="bpltd-rtk-integration-desktop-' . $sourceName . '-adunit-code" class="bpltd-rtk-integration-option" name="bpltd_rtk_integration_settings[sources]['.$sourceName.'][desktop-adunit-code]" value="' . $source['desktop-adunit-code'] . '" placeholder="Ad-unit codes available from the RTK dashboard (RTK_dVa8, RTK_wCZW, RTK_z9hm)" />
                                    </div>
                                </fieldset>
                                    
                                <fieldset class="bpltd-rtk-integration-option-fieldset">
                                    <legend>Quiz</legend>
                                    <div>
                                        <label for="bpltd-rtk-integration-quiz-' . $sourceName . '-auction-code">Auction Unique Id</label>
                                        <input id="bpltd-rtk-integration-quiz-' . $sourceName . '-auction-code" class="bpltd-rtk-integration-option" name="bpltd_rtk_integration_settings[sources]['.$sourceName.'][quiz-auction-code]" value="' . $source['quiz-auction-code'] . '" placeholder="Unique ID (shortcode) for the auction available from the RTK dashboard" />
                                    </div>
                                    
                                    <div>
                                        <label for="bpltd-rtk-integration-quiz-' . $sourceName . '-adunit-code">Ad-Unit Codes</label>
                                        <input id="bpltd-rtk-integration-quiz-' . $sourceName . '-adunit-code" class="bpltd-rtk-integration-option" name="bpltd_rtk_integration_settings[sources]['.$sourceName.'][quiz-adunit-code]" value="' . $source['quiz-adunit-code'] . '" placeholder="Ad-unit codes available from the RTK dashboard (RTK_dVa8, RTK_wCZW, RTK_z9hm)" />
                                    </div>
                                    
                                    <div>
                                        <label for="bpltd-rtk-integration-quiz-' . $sourceName . '-sticky-adunit-code">Sticky Auction Unique Id</label>
                                        <input id="bpltd-rtk-integration-quiz-' . $sourceName . '-sticky-adunit-code" class="bpltd-rtk-integration-option" name="bpltd_rtk_integration_settings[sources]['.$sourceName.'][quiz-sticky-auction-code]" value="' . $source['quiz-sticky-auction-code'] . '" placeholder="Unique ID (shortcode) for the auction available from the RTK dashboard" />
                                    </div>
                                </fieldset>
                                
                                <a href="#" class="bpltd-rtk-integration-option-delete">Delete</a>
                            </fieldset>';
                        }
                    }
                    ?>
                </fieldset>

                <?php
                submit_button();
                ?>
            </form>

            <form id="bpltd-rtk-integration-add-source" method="post">
                <input id="bpltd-rtk-integration-add-source-name" type="text" value="" placeholder="Source Name" />
                <?php
                submit_button("Add Source");
                ?>
            </form>

            <script type="text/javascript">
                jQuery('a.bpltd-rtk-integration-option-delete').on("click", function(event) {
                    event.preventDefault();

                    jQuery(this).parent().remove();
                });

                jQuery('form#bpltd-rtk-integration-add-source').on("submit", function(event) {
                    event.preventDefault();

                    let $this = jQuery(this);
                    let sourceName = $this.find('input#bpltd-rtk-integration-add-source-name').val();
                    $this.find('input#bpltd-rtk-integration-add-source-name').val('');
                    let $sources = jQuery('fieldset#bpltd-rtk-integration-sources');

                    let html = '<fieldset class="bpltd-rtk-integration-option-fieldset bpltd-rtk-integration-option-fieldset-outer">';
                    html += '<legend>' + sourceName + '</legend>';
                    html += '<fieldset class="bpltd-rtk-integration-option-fieldset"><legend>Mobile</legend>';
                    html += '<div>';
                    html += '<label for="bpltd-rtk-integration-mobile-' + sourceName + '-auction-code">Auction Unique Id (Mobile)</label>';
                    html += '<input id="bpltd-rtk-integration-mobile-' + sourceName + '-auction-code" class="bpltd-rtk-integration-option" name="bpltd_rtk_integration_settings[sources][' + sourceName + '][mobile-auction-code]" value="" placeholder="Unique ID (shortcode) for the auction available from the RTK dashboard" />';
                    html += '</div><div>';
                    html += '<label for="bpltd-rtk-integration-mobile-' + sourceName + '-adunit-code">Ad-Unit Codes (Mobile)</label>';
                    html += '<input id="bpltd-rtk-integration-mobile-' + sourceName + '-adunit-code" class="bpltd-rtk-integration-option" name="bpltd_rtk_integration_settings[sources][' + sourceName + '][mobile-adunit-code]" value="" placeholder="Ad-unit codes available from the RTK dashboard (RTK_dVa8, RTK_wCZW, RTK_z9hm)" />';
                    html += '</div><div>';
                    html += '<label for="bpltd-rtk-integration-mobile-' + sourceName + '-sticky-adunit-code">Sticky Auction Unique Id (Mobile)</label>';
                    html += '<input id="bpltd-rtk-integration-mobile-' + sourceName + '-sticky-adunit-code" class="bpltd-rtk-integration-option" name="bpltd_rtk_integration_settings[sources][' + sourceName + '][mobile-sticky-auction-code]" value="" placeholder="Unique ID (shortcode) for the auction available from the RTK dashboard" />';
                    html += '</div>';
                    html += '</fieldset><fieldset class="bpltd-rtk-integration-option-fieldset"><legend>Desktop</legend>';
                    html += '<div>';
                    html += '<label for="bpltd-rtk-integration-desktop-' + sourceName + '-auction-code">Auction Unique Id (Desktop)</label>';
                    html += '<input id="bpltd-rtk-integration-desktop-' + sourceName + '-auction-code" class="bpltd-rtk-integration-option" name="bpltd_rtk_integration_settings[sources][' + sourceName + '][desktop-auction-code]" value="" placeholder="Unique ID (shortcode) for the auction available from the RTK dashboard" />';
                    html += '</div><div>';
                    html += '<label for="bpltd-rtk-integration-desktop-' + sourceName + '-adunit-code">Ad-Unit Codes (Desktop)</label>';
                    html += '<input id="bpltd-rtk-integration-desktop-' + sourceName + '-adunit-code" class="bpltd-rtk-integration-option" name="bpltd_rtk_integration_settings[sources][' + sourceName + '][desktop-adunit-code]" value="" placeholder="Ad-unit codes available from the RTK dashboard (RTK_dVa8, RTK_wCZW, RTK_z9hm)" />';
                    html += '</div>';
                    html += '</fieldset><fieldset class="bpltd-rtk-integration-option-fieldset"><legend>Quiz</legend>';
                    html += '<div>';
                    html += '<label for="bpltd-rtk-integration-quiz-' + sourceName + '-auction-code">Auction Unique Id (Quiz)</label>';
                    html += '<input id="bpltd-rtk-integration-quiz-' + sourceName + '-auction-code" class="bpltd-rtk-integration-option" name="bpltd_rtk_integration_settings[sources][' + sourceName + '][quiz-auction-code]" value="" placeholder="Unique ID (shortcode) for the auction available from the RTK dashboard" />';
                    html += '</div><div>';
                    html += '<label for="bpltd-rtk-integration-quiz-' + sourceName + '-adunit-code">Ad-Unit Codes (Quiz)</label>';
                    html += '<input id="bpltd-rtk-integration-quiz-' + sourceName + '-adunit-code" class="bpltd-rtk-integration-option" name="bpltd_rtk_integration_settings[sources][' + sourceName + '][quiz-adunit-code]" value="" placeholder="Ad-unit codes available from the RTK dashboard (RTK_dVa8, RTK_wCZW, RTK_z9hm)" />';
                    html += '</div><div>';
                    html += '<label for="bpltd-rtk-integration-quiz-' + sourceName + '-sticky-adunit-code">Sticky Auction Unique Id (Quiz)</label>';
                    html += '<input id="bpltd-rtk-integration-quiz-' + sourceName + '-sticky-adunit-code" class="bpltd-rtk-integration-option" name="bpltd_rtk_integration_settings[sources][' + sourceName + '][quiz-sticky-auction-code]" value="" placeholder="Unique ID (shortcode) for the auction available from the RTK dashboard" />';
                    html += '</div>';
                    html += '</fieldset>';
                    html += '<a href="#" class="bpltd-rtk-integration-option-delete">Delete</a>';
                    html += '</fieldset>';

                    let $html = jQuery(html);
                    $html.find('a.bpltd-rtk-integration-option-delete').on("click", function(event) {
                        event.preventDefault();
                        jQuery(this).parent().remove();
                    });

                    jQuery('p.bpltd-rtk-integration-no-sources').remove();

                    $sources.append($html);
                });
            </script>
            <?php
        }

        public function bpltd_rtk_add_scripts()
        {
            if ($this->bpltd_check_can_load()) {
                if (wp_is_mobile()) {
                    $auctionCode = $this->settings['mobile-auction-code'];
                    $adUnitURICodes = $this->getAdUnitURIString($this->settings['mobile-adunit-code']);

                    if (isset($this->settings['mobile-sticky-auction-code']) && trim($this->settings['mobile-sticky-auction-code']) !== '') {
                        echo '<script type="text/javascript" src="//509.hostedprebid.com/' . $this->settings['mobile-sticky-auction-code'] . '/jita_sticky.js" async defer></script>';
                    }
                } else {
                    $auctionCode = $this->settings['desktop-auction-code'];
                    $adUnitURICodes = $this->getAdUnitURIString($this->settings['desktop-adunit-code']);

                    if (isset($this->settings['mobile-sticky-auction-code']) && trim($this->settings['mobile-sticky-auction-code']) !== '') {
                        echo '<script type="text/javascript" src="//509.hostedprebid.com/' . $this->settings['mobile-sticky-auction-code'] . '/jita_sticky.js" async defer></script>';
                    }
                }

                echo '<script type="text/javascript" src="//509.hostedprebid.com/' . $auctionCode . '/' . $adUnitURICodes . '/jita.js?dfp=1" async defer></script>';
            }
        }

        private function getAdUnitURIString($str)
        {
            $arr = explode(',', $str);
            foreach ($arr as $k=>$v) {
                $arr[$k] = str_replace('RTK_', '', trim($v));
            }
            $str = implode('_', $arr);
            return $str;
        }

        private function getAdUnitsAsArray($str)
        {
            $arr = explode(',', $str);
            foreach ($arr as $k=>$v) {
                $arr[$k] = trim($v);
            }
            return $arr;
        }

        public function bpltd_rtk_add_script()
        {
            if (wp_is_mobile() && $this->bpltd_check_can_load()) {
                wp_enqueue_script('bpltd-rtk-plugin-js', BPLTD_RTK_JS_DIR . 'site-mob.js', array('jquery'), BPLTD_RTK_ASSET_VER);
                wp_enqueue_style( 'bpltd-rtk-plugin-css', BPLTD_RTK_CSS_DIR . 'site-mob.css', array(), BPLTD_RTK_ASSET_VER, false);
            }

            if (!wp_is_mobile() && $this->bpltd_check_can_load()) {
                wp_enqueue_script('bpltd-rtk-plugin-js', BPLTD_RTK_JS_DIR . 'site-desk.js', array('jquery'), BPLTD_RTK_ASSET_VER);
                wp_enqueue_style( 'bpltd-rtk-plugin-css', BPLTD_RTK_CSS_DIR . 'site-desk.css', array(), BPLTD_RTK_ASSET_VER, false);
            }
        }

        public function bpltd_rtk_adunit_top_shortcode()
        {
            if (wp_is_mobile() && $this->bpltd_check_can_load()) {
                $adUnits = $this->getAdUnitsAsArray($this->settings['mobile-adunit-code']);

                if ($this->topLoaded) {
                    $adUnitId = $adUnits[0].'_'.uniqid();
                } else {
                    $adUnitId = $adUnits[0];
                }

                $this->topLoaded = true;

                $html = '<div class="rtkadunit-wrapper mobile">';
	            $html .= '<div class="rtkadunit-wrapper-sticky">';
                $html .= '<div class="ad-separator-wrapper"><span class="ad-separator">ADVERTISEMENT</span></div>';
                $html .= '<div id="'.$adUnitId.'" class="'.$adUnits[0].' rtkadunit" >&nbsp;</div>';
	            $html .= '</div>';
                $html .= '</div>';
                return $html;
            }
        }

        public function bpltd_rtk_adunit_middle_shortcode()
        {
            if ($this->bpltd_check_can_load()) {
                if (wp_is_mobile()) {
                    $adUnits = $this->getAdUnitsAsArray($this->settings['mobile-adunit-code']);

                    if ($this->middleLoaded) {
                        $adUnitId = $adUnits[1].'_'.uniqid();
                    } else {
                        $adUnitId = $adUnits[1];
                    }

                    $this->middleLoaded = true;

                    $html = '<div class="rtkadunit-wrapper mobile">';
	                $html .= '<div class="rtkadunit-wrapper-sticky">';
                    $html .= '<div class="ad-separator-wrapper"><span class="ad-separator">ADVERTISEMENT</span></div>';
                    $html .= '<div id="'.$adUnitId.'" class="' . $adUnits[1] . ' rtkadunit" >&nbsp;</div>';
                    $html .= '</div>';
                    $html .= '</div>';
                } else {
                    $adUnits = $this->getAdUnitsAsArray($this->settings['desktop-adunit-code']);

                    if ($this->middleLoaded) {
                        $adUnitId = $adUnits[3].'_'.uniqid();
                    } else {
                        $adUnitId = $adUnits[3];
                    }

                    $this->middleLoaded = true;

                    $html = '<div class="rtkadunit-wrapper desktop">';
                    $html .= '<div class="ad-separator-wrapper"><span class="ad-separator">ADVERTISEMENT</span></div>';
                    $html .= '<div id="'.$adUnitId.'" class="' . $adUnits[3] . ' rtkadunit banner" >&nbsp;</div>';
                    $html .= '</div>';
                }
                return $html;
            }
        }

        public function bpltd_rtk_adunit_bottom_shortcode()
        {
            if ($this->bpltd_check_can_load()) {
                if (wp_is_mobile()) {
                    $adUnits = $this->getAdUnitsAsArray($this->settings['mobile-adunit-code']);

                    if ($this->bottomLoaded) {
                        $adUnitId = $adUnits[2].'_'.uniqid();
                    } else {
                        $adUnitId = $adUnits[2];
                    }

                    $this->bottomLoaded = true;

                    $html = '<div class="rtkadunit-wrapper mobile">';
	                $html .= '<div class="rtkadunit-wrapper-sticky">';
                    $html .= '<div class="ad-separator-wrapper"><span class="ad-separator">ADVERTISEMENT</span></div>';
                    $html .= '<div id="'.$adUnitId.'" class="' . $adUnits[2] . ' rtkadunit" >&nbsp;</div>';
                    $html .= '</div>';
                    $html .= '</div>';
                } else {
                    $adUnits = $this->getAdUnitsAsArray($this->settings['desktop-adunit-code']);

                    if ($this->desktopLoaded) {
                        $adUnitIdLeft = $adUnits[1].'_'.uniqid();
                        $adUnitIdRight = $adUnits[0].'_'.uniqid();
                        $adUnitIdMiddle = $adUnits[2].'_'.uniqid();
                    } else {
                        $adUnitIdLeft = $adUnits[1];
                        $adUnitIdRight = $adUnits[0];
                        $adUnitIdMiddle = $adUnits[2];
                    }

                    $this->desktopLoaded = true;

                    $html = '</div>

                        <section class="grid-item content-right">
                            <div class="content-sticky">
                                <div id="'.$adUnitIdRight.'" class="' . $adUnits[0] . ' rtkadunit"></div>
                            </div>
                        </section>
                    </div>
                    
                    <section class="break">
                        <div id="'.$adUnitIdMiddle.'" class="' . $adUnits[2] . ' rtkadunit">&nbsp;</div>
                    </section>
                    
                    <div class="grid-container content">
                        <section class="grid-item content-left">
                            <div class="content-sticky">
                                <div id="'.$adUnitIdLeft.'" class="' . $adUnits[1] . ' rtkadunit"></div>
                            </div>
                        </section>
                        
                        <div class="grid-item content-middle">';
                }

                return $html;
            }
        }

        public function bpltd_rtk_adunit_end_shortcode()
        {
            if ($this->bpltd_check_can_load()) {
                if (!wp_is_mobile()) {
                    $adUnits = $this->getAdUnitsAsArray($this->settings['desktop-adunit-code']);

                    $adUnitIdRight = $adUnits[0].'_'.uniqid();
                    $adUnitIdEnd = $adUnits[2].'_'.uniqid();

                    $html = '</div>
                        <section class="grid-item content-right">
                            <div class="content-sticky">
                                <div id="'.$adUnitIdRight.'" class="' . $adUnits[0] . ' rtkadunit"></div>
                            </div>
                        </section>
                    </div>
                    
                    <section class="break end">
                        <div id="'.$adUnitIdEnd.'" class="' . $adUnits[2] . ' rtkadunit">&nbsp;</div>
                    </section>';

                    return $html;
                }
            }
        }

        public function bpltd_rtk_adunit_shortcode($attributes)
        {
            $arrAtts = shortcode_atts(array(
                'desktop' => '',
                'mobile' => '',
                'desktop_banner' => '',
                'desktop_last' => ''
            ), $attributes);

            if (wp_is_mobile() && $this->bpltd_check_can_load()) {
                if (!empty($arrAtts['mobile'])) {
                    if ($this->mobileLoaded) {
                        $adUnitId = $arrAtts['mobile'].'_'.uniqid();
                    } else {
                        $adUnitId = $arrAtts['mobile'];
                    }

                    $this->mobileLoaded = true;

                    return '<div class="rtkadunit-wrapper mobile">
                                <div class="rtkadunit-wrapper-sticky">
                                    <div class="ad-separator-wrapper"><span class="ad-separator">ADVERTISEMENT</span></div>
                                    <div id="'.$adUnitId.'" class="' . $arrAtts['mobile'] . ' rtkadunit" >&nbsp;</div>
                                </div>
                            </div>';
                }
            } else if (!wp_is_mobile() && $this->bpltd_check_can_load()) {
                if (!empty($arrAtts['desktop'])) {
                    if ($this->desktopLoaded) {
                        $adUnitIdLeft = 'RTK_3DBG_'.uniqid();
                        $adUnitIdRight = 'RTK_3luH_'.uniqid();
                        $adUnitIdMiddle = $arrAtts['desktop'].'_'.uniqid();
                    } else {
                        $adUnitIdLeft = 'RTK_3DBG';
                        $adUnitIdRight = 'RTK_3luH';
                        $adUnitIdMiddle = $arrAtts['desktop'];
                    }

                    $this->desktopLoaded = true;

                    return '</div>
                        <section class="grid-item content-right">
                            <div class="content-sticky">
                                <div id="'.$adUnitIdRight.'" class="RTK_3luH rtkadunit"></div>
                            </div>
                        </section>
                    </div>
                    
                    <section class="break">
                        <div id="'.$adUnitIdMiddle.'" class="' . $arrAtts['desktop'] . ' rtkadunit">&nbsp;</div>
                    </section>
                    
                    <div class="grid-container content">
                        <section class="grid-item content-left">
                            <div class="content-sticky">
                                <div id="'.$adUnitIdLeft.'" class="RTK_3DBG rtkadunit"></div>
                            </div>
                        </section>
                        
                        <div class="grid-item content-middle">';
                } else if (!empty($arrAtts['desktop_banner'])) {
                    return '<div class="rtkadunit-wrapper desktop"><div class="ad-separator-wrapper"><span class="ad-separator">ADVERTISEMENT</span></div><div class="' . $arrAtts['desktop_banner'] . ' rtkadunit banner" >&nbsp;</div></div>';
                } else if (!empty($arrAtts['desktop_last'])) {
                    return '</div>
                        <section class="grid-item content-right">
                            <div class="content-sticky">
                                <div class="RTK_3luH rtkadunit"></div>
                            </div>
                        </section>
                    </div>
                    
                    <section class="break">
                        <div class="' . $arrAtts['desktop_last'] . ' rtkadunit">&nbsp;</div>
                    </section>';
                } else if (!empty($arrAtts['mobile'])) {
                    switch ($arrAtts['mobile']) {
                        case 'RTK_wCZW':
                            return '<div class="rtkadunit-wrapper desktop"><div class="ad-separator-wrapper"><span class="ad-separator">ADVERTISEMENT</span></div><div class="RTK_L9Y2 rtkadunit banner" >&nbsp;</div></div>';
                            break;
                        case 'RTK_z9hm':
                            return '</div>
                                <section class="grid-item content-right">
                                    <div class="content-sticky">
                                        <div class="RTK_3luH rtkadunit"></div>
                                    </div>
                                </section>
                            </div>
                            
                            <section class="break">
                                <div class="RTK_I7VB rtkadunit">&nbsp;</div>
                            </section>
                            
                            <div class="grid-container content">
                                <section class="grid-item content-left">
                                    <div class="content-sticky">
                                        <div class="RTK_3DBG rtkadunit"></div>
                                    </div>
                                </section>
                                
                                <div class="grid-item content-middle">';
                            break;
                    }
                }
            }
            return '';
        }

        private function bpltd_check_can_load() {
            global  $post;

            return $post &&
                get_post_type() == 'post' &&
                (get_page_template_slug($post->ID) == 'single-onepage.php' || get_page_template_slug($post->ID) == 'single-playbuzz.php') &&
                !$this->bpltd_is_bot($_SERVER['HTTP_USER_AGENT']);
        }

        public function bpltd_is_bot($sistema){
            $bots = array(
                'Googlebot', 'Baiduspider', 'ia_archiver', 'R6_FeedFetcher', 'NetcraftSurveyAgent', 'Sogou web spider'
            , 'bingbot', 'Yahoo! Slurp', 'facebookexternalhit', 'PrintfulBot', 'msnbot', 'Twitterbot', 'UnwindFetchor'
            , 'urlresolver', 'Butterfly', 'TweetmemeBot', 'PaperLiBot', 'MJ12bot', 'AhrefsBot', 'Exabot', 'Ezooms'
            , 'YandexBot', 'SearchmetricsBot', 'picsearch', 'TweetedTimes Bot', 'QuerySeekerSpider', 'ShowyouBot'
            , 'woriobot', 'merlinkbot', 'BazQuxBot', 'Kraken', 'SISTRIX Crawler', 'R6_CommentReader', 'magpie-crawler'
            , 'GrapeshotCrawler', 'PercolateCrawler', 'MaxPointCrawler', 'R6_FeedFetcher', 'NetSeer crawler'
            , 'grokkit-crawler', 'SMXCrawler', 'PulseCrawler', 'Y!J-BRW', '80legs.com/webcrawler', 'Mediapartners-Google'
            , 'Spinn3r', 'InAGist', 'Python-urllib', 'NING', 'TencentTraveler', 'Feedfetcher-Google', 'mon.itor.us'
            , 'spbot', 'Feedly', 'bitlybot', 'ADmantX Platform', 'Niki-Bot', 'Pinterest', 'python-requests'
            , 'DotBot', 'HTTP_Request2', 'linkdexbot', 'A6-Indexer', 'Baiduspider', 'TwitterFeed', 'Microsoft Office'
            , 'Pingdom', 'BTWebClient', 'KatBot', 'SiteCheck', 'proximic', 'Sleuth', 'Abonti', '(BOT for JCE)'
            , 'Baidu', 'Tiny Tiny RSS', 'newsblur', 'updown_tester', 'linkdex', 'baidu', 'searchmetrics'
            , 'genieo', 'majestic12', 'spinn3r', 'profound', 'domainappender', 'VegeBot', 'terrykyleseoagency.com'
            , 'CommonCrawler Node', 'AdlesseBot', 'metauri.com', 'libwww-perl', 'rogerbot-crawler', 'MegaIndex.ru'
            , 'ltx71', 'Qwantify', 'Traackr.com', 'Re-Animator Bot', 'Pcore-HTTP', 'BoardReader', 'omgili'
            , 'okhttp', 'CCBot', 'Java/1.8', 'semrush.com', 'feedbot', 'CommonCrawler', 'AdlesseBot', 'MetaURI'
            , 'ibwww-perl', 'rogerbot', 'MegaIndex', 'BLEXBot', 'FlipboardProxy', 'techinfo@ubermetrics-technologies.com'
            , 'trendictionbot', 'Mediatoolkitbot', 'trendiction', 'ubermetrics', 'ScooperBot', 'TrendsmapResolver'
            , 'Nuzzel', 'Go-http-client', 'Applebot', 'LivelapBot', 'GroupHigh', 'SemrushBot', 'ltx71', 'commoncrawl'
            , 'istellabot', 'DomainCrawler', 'cs.daum.net', 'StormCrawler', 'GarlikCrawler', 'The Knowledge AI'
            , 'getstream.io/winds', 'YisouSpider', 'archive.org_bot', 'semantic-visions.com', 'FemtosearchBot'
            , '360Spider', 'linkfluence.com', 'glutenfreepleasure.com', 'Gluten Free Crawler', 'YaK/1.0'
            , 'Cliqzbot', 'app.hypefactors.com', 'axios', 'semantic-visions.com', 'webdatastats.com', 'schmorp.de'
            , 'SEOkicks', 'DuckDuckBot', 'Barkrowler', 'ZoominfoBot', 'Linguee Bot', 'Mail.RU_Bot', 'OnalyticaBot'
            , 'Linguee Bot', 'admantx-adform', 'Buck/2.2', 'Barkrowler', 'Zombiebot', 'Nutch', 'SemanticScholarBot'
            , 'Jetslide', 'scalaj-http', 'XoviBot', 'sysomos.com', 'PocketParser', 'newspaper', 'serpstatbot'
            , 'MetaJobBot', 'SeznamBot/3.2', 'VelenPublicWebCrawler/1.0', 'WordPress.com mShots', 'adscanner'
            , 'BacklinkCrawler', 'netEstate NE Crawler', 'Astute SRM', 'GigablastOpenSource/1.0', 'DomainStatsBot'
            , 'Winds: Open Source RSS & Podcast', 'dlvr.it', 'BehloolBot', '7Siters', 'AwarioSmartBot'
            , 'Apache-HttpClient/5', 'Google Page Speed Insights', 'Chrome-Lighthouse'
            );

            foreach($bots as $b) {
                if( stripos( $sistema, $b ) !== false ) return true;
            }

            return false;
        }
    }

    $bpltdRTK = new BPLTD_RTK_Integration();
}
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
        private $displayedAdCount = 0;

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

            $this->source = (isset($_GET['utm_source'])) ? $_GET['utm_source'] : null;

            $settings = get_option('bpltd_rtk_integration_settings', []);

            $sources = array_reverse($settings['sources']);
            $this->settings = array_pop($sources);

            if ($this->source !== null) {
                if (array_key_exists($this->source, $settings['sources'])) {
                    $this->settings = $settings['sources'][$this->source];
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
            wp_enqueue_script('bpltd-rtk-plugin-js', BPLTD_RTK_JS_DIR . 'site.js', array('jquery'), BPLTD_RTK_ASSET_VER);
            wp_enqueue_style( 'bpltd-rtk-plugin-css', BPLTD_RTK_CSS_DIR . 'site.css', array(), BPLTD_RTK_ASSET_VER, false);
        }

        public function bpltd_rtk_adunit_shortcode()
        {
            if (wp_is_mobile() && $this->bpltd_check_can_load()) {
                $adUnits = $this->getAdUnitsAsArray($this->settings['mobile-adunit-code']);
                $totalAdUnits = count($adUnits);

                $adUnitId = $adUnits[$this->displayedAdCount].'_'.uniqid();

                $html = '<div class="rtkadunit-wrapper mobile">';
                // TODO: implement Wordpress settings for scrolling ads then uncomment this
	            //$html .= '<div class="rtkadunit-wrapper-sticky">';
                $html .= '<div class="ad-separator-wrapper"><span class="ad-separator">ADVERTISEMENT</span></div>';
                $html .= '<div id="'.$adUnitId.'" class="'.$adUnits[0].' rtkadunit" >&nbsp;</div>';
                $html .= '<div class="ad-separator-wrapper"><span class="ad-separator">ADVERTISEMENT</span></div>';
                // TODO: implement Wordpress settings for scrolling ads then uncomment this
	            //$html .= '</div>';
                $html .= '</div>';

                if (($this->displayedAdCount+1) === $totalAdUnits) {
                    $this->displayedAdCount = 0;
                } else {
                    $this->displayedAdCount++;
                }

                return $html;
            }
        }

        private function bpltd_check_can_load() {
            global  $post;

            return $post && !$this->bpltd_is_bot($_SERVER['HTTP_USER_AGENT']);
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
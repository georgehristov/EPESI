<?php

/**
 * Index file
 *
 * Inits EpesiIndex object and displays index file.
 *
 * @author    Paul Bukowski <pbukowski@telaxus.com>
 *            Adam Bukowski <abukowski@telaxus.com
 * @copyright Copyright &copy; 2014, Telaxus LLC
 * @license   MIT
 * @version   1.0
 * @package   epesi-base
 */

/**
 * Class EpesiIndex
 *
 * Includes all necessary files to start javascript and init EPESI.
 * Print base html template with necessary styles and scripts.
 *
 * @author    Adam Bukowski <abukowski@telaxus.com>
 * @copyright Copyright &copy; 2014, Telaxus LLC
 * @license   MIT
 * @version   1.0
 * @package   epesi-base
 */
class EpesiIndex
{

    public function show()
    {
        $this->check_requirements();

        $this->load_data_dir_path();

        if (!$this->epesi_installed()) {
            $this->redirect_to_setup();
        } else {
            $this->load_and_show();
        }
    }

    private function check_requirements()
    {
        $system_php_version = phpversion();
        $required_php_version = '5.3';
        if (version_compare(phpversion(), $required_php_version, '<')) {
            die("You are running too old version of PHP. At least $required_php_version is required. You're using $system_php_version");
        }

        if (trim(ini_get("safe_mode"))) {
            die('You cannot use EPESI with PHP safe mode turned on - please disable it. Please notice this feature is deprecated since PHP 5.3 and will be removed in PHP 6.0.');
        }
    }

    private function load_data_dir_path()
    {
        define('_VALID_ACCESS', 1);
        require_once('include/data_dir.php');
    }

    private function epesi_installed()
    {
        return file_exists(DATA_DIR . '/config.php');
    }

    private function redirect_to_setup()
    {
        header('Location: setup.php');
    }

    private function load_and_show()
    {
        require_once('include/config.php');
        require_once('include/maintenance_mode.php');
        require_once('include/error.php');
        ob_start(array('ErrorHandler', 'handle_fatal'));
        require_once('include/database.php');
        require_once('include/variables.php');
        require_once('include/misc.php');

        if ($this->update_available()) {
            $this->update_process();
        } else {
            $this->show_index();
        }

        $content = ob_get_clean();

        require_once('libs/minify/HTTP/Encoder.php');
        $he = new HTTP_Encoder(array('content' => $content));
        if (MINIFY_ENCODE) {
            $he->encode();
        }
        $he->sendAll();
    }

    private function update_available()
    {
        $installed_version = Variable::get('version');
        return $installed_version !== EPESI_VERSION;
    }

    private function update_process()
    {
        if (isset($_GET['up'])) {
            require_once('update.php');
            $retX = ob_get_clean();
            if (trim($retX)) {
                die($retX);
            }
            header('Location: index.php');
        } else {
            $title = EPESI . " update";
            $this->print_html_header($title);
            $this->print_update_message();
            $this->print_html_footer(true);
        }
    }

private function print_html_header($title)
{
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php print($title); ?></title>
        <link rel="icon" type="image/png" href="images/favicon.png"/>
        <!-- Apple icon -->
        <link rel="apple-touch-icon" href="images/apple-favicon.png"/>
        <!-- Disable Skype to parse html -->
        <meta name="SKYPE_TOOLBAR" content="SKYPE_TOOLBAR_PARSER_COMPATIBLE"/>
        <meta name="robots" content="NOINDEX, NOARCHIVE">

        <link type="text/css" href="libs/jquery-ui-1.10.1.custom.min.css"
              rel="stylesheet">
        <link type="text/css" href="style/css/epesi.css" rel="stylesheet">
        <?php if (DIRECTION_RTL) { ?>
            <style type="text/css">
                body {
                    direction: rtl;
                }
            </style>
        <?php
        }
        // serve.php reduces transfer with gzip compression
        ini_set('include_path', 'libs/minify' . PATH_SEPARATOR . '.' . PATH_SEPARATOR . 'libs' . PATH_SEPARATOR . ini_get('include_path'));
        require_once('Minify/Build.php');
        $jses = array('libs/prototype.js',
                      'libs/jquery-1.7.2.min.js',
                      'libs/jquery-ui-1.10.1.custom.min.js',
                      'libs/HistoryKeeper.js',
                      'style/js/bootstrap.min.js',
                      'include/epesi.js');
        $jsses_build = new Minify_Build($jses);
        $jsses_src = $jsses_build->uri('serve.php?' . http_build_query(array('f' => array_values($jses))));
        ?>
        <script type="text/javascript"
                src="<?php print($jsses_src) ?>"></script>
        <style type="text/css">
            <?php if (DIRECTION_RTL) {print('body { direction: rtl; }');} ?>
        </style>
        <?php print(TRACKING_CODE); ?>
    </head>
    <body<?php if (DIRECTION_RTL) {
        print(' class="epesi_rtl"');
    } ?>>
    <?php
    }

    private function print_update_message()
    {
        $installed_version = Variable::get('version'); ?>
        <div>
            Updating EPESI from version <?php echo $installed_version; ?>
            to <?php echo EPESI_VERSION; ?>. This operation may take several
            minutes.
            <a href="index.php?up=1">Click here to proceed</a>
        </div> <?php
    }

    private function print_html_footer($with_copyright)
    {
    if ($with_copyright) {
        ?>
        <div class="footer">
            <p>Copyright &copy; 2014 &bull; <a href="http://www.telaxus.com">Telaxus
                    LLC</a></p>

            <p><a href="http://www.epesi.org"><img
                        src="images/epesi-powered.png" alt="EPESI powered"></a>
            </p>
        </div> <?php
    } ?>
    </body>
    </html> <?php
}

    private function show_index()
    {
        $this->print_html_header(EPESI);
        $this->print_body_content();
        $this->print_html_footer(false);
    }

    private function print_body_content()
    {
        // TODO: rewrite this html
        ?>
        <div id="body_content">
            <div id="main_content" style="display:none;"></div>
            <div id="debug_content" style="padding-top:97px;display:none;">
                <div class="button"
                     onclick="$('error_box').innerHTML='';$('debug_content').style.display='none';">
                    Hide
                </div>
                <div id="debug"></div>
                <div id="error_box"></div>
            </div>

            <div id="epesiStatus">
                <div><img src="images/logo.png" alt="logo"></div>
                <div
                    id="epesiStatusText"><?php print(STARTING_MESSAGE); ?></div>
                <div id="epesiStatusLoader"><img src="images/loader.gif"
                                                 alt="loader"></div>
            </div>
        </div>
        <?php
        /*
         * init_js file allows only num_of_clients sessions. If there is image
         * with empty src="" browser will load index.php file, so we cannot
         * include init_js file directly because num_of_clients request will
         * reset our history and restart EPESI.
         *
         * Check here if request accepts html. If it does we can assume that
         * this is request for page and include init_js file which is faster.
         * If there is not 'html' in accept use script with src property.
         */
        if (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'html') !== false) {
            ?>
            <script
                type="text/javascript"><?php require_once 'init_js.php'; ?></script>
        <?php } else { ?>
            <script type="text/javascript"
                    src="init_js.php?<?php print(http_build_query($_GET)); ?>"></script>
        <?php } ?>
        <noscript>
            Please enable JavaScript in your browser and
            let <?php print(EPESI); ?> work!
        </noscript>
        <?php if (IPHONE) { ?>
        <script type="text/javascript">var iphone = true;</script>
    <?php
    }
    }
}

$index = new EpesiIndex();
$index->show();

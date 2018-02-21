<?php
/**
 * InOutput.php
 * @author Martin S.
 * @author Stura - Referat IT <ref-it@tu-ilmenau.de>
 * @since 17.02.18 22:28
 */


class InOutput
{
    static function ReadFile($Filepath): Array
    {
        $result = array();
        if (strpos($Filepath, ".txt") !== false) {
            if ($fl = fopen($Filepath, "r")) {
                while (!feof($fl)) {
                    $result[] = fgets($fl);
                }
                fclose($fl);
            }
            return $result;
        }
    }

    static function ReadWiki($wikiPath = ""): Array
    {
        require_once(SYSBASE . '/framework/class.wikiClient.php');
        $x = new wikiClient(WIKI_URL, WIKI_USER, WIKI_PASSWORD, WIKI_XMLRPX_PATH);
        echo '<pre>';
        $completeFile = "";//$x->get($name, $fileAsString) TODO Get Page einbauen
        if (Main::$debug) {
            var_dump(x);
        }
        $result = explode(PHP_EOL, $completeFile);
        return $result;
    }

    static function WriteFile($FileName, $content): bool
    {
        if (Main::$DisableWrite === false) {

            try {
                if ($fl = fopen($FileName, "w+")) {
                    foreach ($content as $line) {
                        fwrite($fl, $line);
                    }
                }
                fclose($fl);
            } catch (Exception $e) {
                Useroutput::PrintHorizontalSeperator();
                Useroutput::PrintLineDebug($e);
                return false;
            }
        }
        if (strpos($FileName, 'help') !== false) {
            return true;
        }
        $check = true;
        do {
            if (Main::$debug) {
                var_dump($FileName);
            }
            if (strpos($FileName, "/") !== false) {
                $FileName = substr($FileName, strpos($FileName, "/") + 1);
            } else {
                $check = false;
            }
            if (Main::$debug) {
                var_dump($FileName);
            }
        } while ($check);
        $FileName = substr($FileName, 0, strlen($FileName) - 4);
        self::WriteTestWiki($FileName, $content);
        return true;
    }

    static function WriteTestWiki($name, $content = [])
    {
        $fileAsString = "";
        foreach ($content as $item) {
            $fileAsString = $fileAsString . $item;
        }
        require_once(SYSBASE . '/framework/class.wikiClient.php');
        $x = new wikiClient(WIKI_URL, WIKI_USER, WIKI_PASSWORD, WIKI_XMLRPX_PATH);
        echo '<pre>';
        if (Main::$debug) {
            var_dump($x->putSpielwiese($name, $fileAsString));
        }
        echo '</pre>';
    }

}

?>
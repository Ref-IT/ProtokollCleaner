<?php

/**
 * Main.php
 * @author Martin S.
 * @author Stura - Referat IT <ref-it@tu-ilmenau.de>
 * @since 18.10.17 09:54
 */

include 'Date.php';
include 'File.php';
include 'Useroutput.php';
include 'InOutput.php';
include 'VisualCopyEmulator.php';
include 'DatabaseConnector.php';

class Main
{
    public static $inputpath; //intern part of the Wiki, where the files which will be cleaned are
    public static $outputpath; //public part of the Wiki, where the cleaned files will be saved
    public static $decissionList; //List off StuRa Decissions
    public static $helperFilePath; //List off StuRa Decissions
    public static $starttag; //start tag of cleaning area
    public static $endtag; //end tag of cleaning area
    public static $debug; //all as Text on Browser
    public static $onlyNew; //only new financial decissions
    public static $postData; //set to true if you want to post data to another website
    public static $PostUrl; //destination for Posting of financial decission list
    public static $startMonth;    //Day,
    public static $startYear;  //Month and
    public static $startday;    //Year of First protokoll which will be cleaned
    public static $copiedLineColor; #Color for copied Line in Diff
    public static $copiedEditedLineColor; #Color for copied edited Line in Diff
    public static $removedLineColor; #Standard Color for removed Line in Diff
    public static $notDoubled; #do you want copy protokolls doubled
    public static $newDecissionList; #Path to new DecissionList
    public static $restDecissionListTitel; #rest Titel after 'week of'

    //Arbeitsvariablen
    public static $financialResolution = array();
    public static $DatabaseCon;
    private $files;


    public function __construct() // or any other method
    {
        if(file_exists(dirname(__FILE__).'/../conf/config.php')) {
            include dirname(__FILE__).'/../conf/config.php';
            Useroutput::PrintLineDebug("Die Config wurde genutzt.");
        }
        else
        {
            include dirname(__FILE__).'/../conf/config.default.php';
            Useroutput::PrintLineDebug("Die Reserve-Config wurde genutzt.");
        }
        Main::$DatabaseCon = new DatabaseConnector();
        Useroutput::PrintHorizontalSeperator();
    }

    public function generateDiff($Protokoll, $check)
    {
        VisualCopyEmulator::generateDiffTable($Protokoll, $check);
    }

    public function Main()
    {
        $this->files = array();
        $alledateien = scandir(Main::$inputpath); //Ordner "files" auslesen
        foreach ($alledateien as $datei) { // Ausgabeschleife
            $length = strlen($datei);
            if((substr($datei, 0,1) == ".") or (substr($datei, $length - 4, 4) != ".txt") or ($length !== 14) )
            {
                continue;
            }
            $Date = $this->getDateFromFileName($datei);
            if ($Date === -1)
            {
                continue;
            }
            if(intval($Date->Year()) < Main::$startYear)
            {
                continue;
            }
            if((intval($Date->Year()) === Main::$startYear) and (intval($Date->Month()) < Main::$startMonth))
            {
                continue;
            }
            if((intval($Date->Year()) === Main::$startYear) and (intval($Date->Month()) === Main::$startMonth) and (intval($Date->Day()) < Main::$startday))
            {
                continue;
            }
            $file = new File($Date, $datei);
            $fn = Main::$outputpath . "/" . $file->getOutputFilename();
            $check = $this->checkApproved($file->getgermanDate());
            if($check)
            {
                if (Main::$DatabaseCon->alreadyPublishedFinal($file->getOutputFilename()) and Main::$notDoubled)
                {
                    Useroutput::PrintLineDebug('Already Published as Final.');
                    continue;
                }
                $Ausgabe = "Published as Final: ";
                Main::$DatabaseCon->newPublishedFinal($file->getOutputFilename());
                if (Main::$DatabaseCon->alreadyPublishedDraft($file->getOutputFilename()))
                {
                    Main::$DatabaseCon->removeFromDraft($file->getOutputFilename());
                }
            }
            else
            {
                if (Main::$DatabaseCon->alreadyPublishedDraft($file->getOutputFilename()) and Main::$notDoubled)
                {
                    Useroutput::PrintLineDebug('Already Published as Draft.');
                    continue;
                }
                $Ausgabe = "Published as Draft: ";
                Main::$DatabaseCon->newPublishedDraft($file->getOutputFilename());
            }
            $Ausgabe = $Ausgabe . $this->copy($file->getFilename(), $fn, $check);
            Useroutput::PrintLine($Ausgabe);
            VisualCopyEmulator::generateDiffTable(InOutput::ReadFile($file->getFilename()), $check);
            $this->files[] = $file;
        }
        Useroutput::PrintHorizontalSeperator();
        $this->exportFinancial();
        if (Main::$postData) {
            $this->sendData();
        }
    }
    function copy($fileName, $fn, $check) : string
    {
        $OffRec=false;
        $lines = array();
        foreach (InOutput::ReadFile($fileName) as $line) {
            # do same stuff with the $line
            if(!$OffRec and strpos($line, "tag>" . Main::$starttag) !== false) {
                $OffRec=true;
                continue;
            }
            if(!$OffRec)
            {
                if(strpos($line, "======") !== false and !$check)
                {
                    $firstpart = substr($line, strpos($line, "======"), 6 );
                    $secondpart = substr($line, strpos($line, "======") + 6, strlen($line) -1 );
                    $lines[] = $firstpart . " Entwurf:" . $secondpart;
                }
                else {
                    $lines[] = $line;
                }
                    continue;
            }
            if($OffRec and strpos($line, "tag>" . Main::$endtag) !== false) {
                $OffRec=false;
            }
        }
        if (InOutput::WriteFile($fn,$lines) === false)
        {
            exit(10);
        }
        return substr($fileName, strlen($fileName)-14, strlen($fileName) -1);
    }

    function getDateFromFileName($Filename)
    {
        $length = strlen($Filename);
        $Name = substr($Filename, 0, $length - 4);
        $check = substr($Name, 0, 10);
        $expression = "/^[12][09][0129][0123456789]-[01][0123456789]-[0123][0123456789]/";
        if(preg_match($expression,$check) === false) {
            Useroutput::PrintLineDebug("File Discarded: " . $Filename);
            return -1;
        }
        $d = substr($Name, 8, 2);
        $m = substr($Name, 5, 2);
        $y = substr($Name, 0, 4);
        $Date = new Date($y,$m,$d);
        return $Date;
    }

    function checkApproved($germanDate)
    {
        foreach (InOutput::ReadFile(Main::$decissionList) as $line)
        {
            # do same stuff with the $line
            if ((strpos($line, "beschließt") !== false) and  (strpos($line, "Protokoll") !== false ) and (strpos($line, "Sitzung") !== false ) and (strpos($line, $germanDate) !== false)) {
                return true;
            }
        }
        return false;
    }

    function exportFinancial()
    {
        foreach (InOutput::ReadFile(Main::$decissionList) as $line)
        {
            if ((strpos($line, "Budget") !== false)) {
                $lineStart = $this->getLineStartFinancialDeccision($line);
                if($this->checkAlreadyPostedData($lineStart))
                {
                    continue;
                }
                if (strpos($line, "https://helfer.stura.tu-ilmenau.de/FinanzAntragUI/") !== false) {
                    if (!(strpos($line, "<del>") !== false))
                    {
                        $line = $this->formatLine($line, true, $lineStart);
                        if (strpos($line, "AlreadyInside") === false) {
                            Useroutput::PrintLineDebug($line);
                        }
                    }
                }
                else
                {
                    if ((!(strpos($line, "<del>") !== false)) and ! Main::$onlyNew)
                    {
                        $line = $this->formatLine($line, false, $lineStart);
                        if (strpos($line, "AlreadyInside") === false) {
                            Useroutput::PrintLineDebug($line);
                        }
                    }
                }
            }
        }
    }

    function getLineStartFinancialDeccision ($line) :string
    {
        $lineStart = substr($line, strpos($line, "|")+1);
        $lineStart = substr($lineStart, 0, strpos($lineStart, "|"));
        $lineStart = str_replace(" ", "", $lineStart);
        return $lineStart;
    }
    function formatLine($line, $withToken, $lineStart)
    {
        if($withToken)
        {
            $lineEnd = substr($line, strpos($line, "|")+1);
            $lineEnd = substr($lineEnd, strpos($lineEnd, "|") +1 );
            $lineEnd = substr($lineEnd, strpos($lineEnd, "|") +1 );
            $lineEnd = substr($lineEnd, strpos($lineEnd, "FinanzAntragUI/") + 15);
            $lineEnd = substr($lineEnd, 0 , strpos($lineEnd, "|") );
            $lineEnd = str_replace(" ", "", $lineEnd);
            $lineS = $lineStart . "#-#" . $lineEnd;
            Main::$financialResolution[$lineEnd] = $lineStart;
        }
        else
        {
            Main::$financialResolution[] = $lineStart;
            $lineS = $lineStart . "#-#" . "not found";
        }
        Main::$DatabaseCon->newFinancialDecission($lineStart);
        return $lineS;
    }

    function sendData()
    {
        $url = Main::$PostUrl;
        $content = json_encode(Main::$financialResolution); //PHP Array

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
        //execute Postback
        $ret = curl_exec($curl);
        Useroutput::PrintLineDebug($ret);  //kann auch eine bel. if abfrage zum testen des ergebnisses sein
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE); //tut bestimmt sinnvolle dinge
        Useroutput::PrintLineDebug($status);
        Useroutput::PrintHorizontalSeperator();
        if(strpos($status, "200") !== false)
        {
            Useroutput::PrintLine("Daten wurden erfolgeich übertragen.");
        }
        else
        {
            exit($status);
        }
        curl_close($curl); //beendet verbindung, oder so
    }

    function checkAlreadyPostedData($DecissionKey):bool
    {
        return Main::$DatabaseCon->knownDecissionFinancial($DecissionKey);
    }
}

?>
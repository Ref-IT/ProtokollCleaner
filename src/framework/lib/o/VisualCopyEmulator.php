<?php
/**
 * search for internal part in protocolls
 * checks open and closing tags
 * VisualCopyEmulator.php
 * @author Martin S.
 * @author Stura - Referat IT <ref-it@tu-ilmenau.de>
 * @since 18.02.18 18:02
 */

class VisualCopyEmulator
{
    public static function generateDiffTable($Protokoll, $check)
    {
        self::generateHeader();
        $OffRec = false;
        $countInTag = 0;
        $countOutTag = 0;
        foreach ($Protokoll as $ln => $line) //loop throught $protocol lines
        {
            if (strpos($line, "tag>" . Main::$starttag) !== false)
            {
                $countInTag = $countInTag + 1;
            }
            if (strpos($line, "tag>" . Main::$endtag) !== false)
            {
                if ($countInTag === 0)
                {
                    Useroutput::PrintLine("<p>Warning Endtag vor Anfangstag</p><br />" .PHP_EOL);
                }
                if ($countInTag === $countOutTag)
                {
                    Useroutput::PrintLine("<p>Warning Endtag vor Anfangstag</p><br />" .PHP_EOL);
                }
                $countOutTag = $countOutTag + 1;
            }
            if(!$OffRec and strpos($line, "tag>" . Main::$starttag) !== false) {
                $OffRec=true;
                self::generateRemovedLine($line, $ln + 1);
                continue;
            }
            if(!$OffRec)
            {
                if(strpos($line, "======") !== false and !$check)
                {
                    $firstpart = substr($line, strpos($line, "======"), 6 );
                    $secondpart = substr($line, strpos($line, "======") + 6, strlen($line) -1 );
                    $newTitel = $firstpart . " Entwurf:" . $secondpart;
                    self::generateCopiedChangedLine($newTitel, $ln + 1);
                }
                else {
                    self::generateCopiedLine($line, $ln + 1);
                }
                continue;
            }
            if($OffRec and strpos($line, "tag>" . Main::$endtag) !== false) {
                $OffRec=false;
            }
            self::generateRemovedLine($line, $ln);
        }
        self::generateFooter();
    }
    //write table header to stdout
    private static function generateHeader()
    {
    	$head=
     	"<div class='difftable'>\n".
     		"<div class='headline'>\n".
     			"<span>Linenumber</span>\n".
     			"<span>+</span>\n".
     			"<span>-</span>\n".
     			"<span>C</span>\n".
     			"<span>Content</span>\n".
     		"</div>\n";
        Useroutput::Print($head);
    }
    //write removed protocol line (red)
    private static function generateRemovedLine($line, $ln)
    {
        $lineresult = 
        "<div class='line removed'>\n".
        	"<span></span>\n".
        	"<span></span>\n".
        	"<span>-</span>\n".
        	"<span></span>\n".
        	'<span>'.htmlspecialchars($line)."</span>\n".
        "</div>\n";
        Useroutput::Print($lineresult);
    }
    //write normal copied protocol line (white)
    private static function generateCopiedLine($line, $ln)
    {
    	$lineresult =
    	"<div class='line normal'>\n".
	    	"<span>$ln</span>\n".
	    	"<span>+</span>\n".
	    	"<span></span>\n".
	    	"<span></span>\n".
	    	'<span>'.htmlspecialchars($line)."</span>\n".
    	"</div>\n";
        Useroutput::Print($lineresult);
    }
    //write changed protocol line (gray)
    private static function generateCopiedChangedLine($line, $ln)
    {
    	$lineresult =
    	"<div class='line changed'>\n".
	    	"<span>$ln</span>\n".
	    	"<span>+</span>\n".
	    	"<span></span>\n".
	    	"<span>C</span>\n".
	    	'<span>'.htmlspecialchars($line)."</span>\n".
    	"</div>\n";
        Useroutput::Print($lineresult);
    }
    //write table footer to stdout
    private static function generateFooter()
    {
        $footer="</div>\n";
        Useroutput::PrintLine($footer);
    }
}
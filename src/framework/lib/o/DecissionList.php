<?php
/**
 * DecissionList.php
 * @author Martin S.
 * @author Stura - Referat IT <ref-it@tu-ilmenau.de>
 * @since 19.02.18 01:06
 */

class DecissionList
{
    private $DecissioList;
    private $newDecissions;
    private $TodoList;
    private $TodoListDebug;
    private $wiki = false;

    public function __construct($wiki = false) // or any other method
    {
        $this->wiki = $wiki;
        $this->newDecissions = array();
        $this->DecissioList = Array();
        $this->TodoList = Array();
        if (Main::$enableToDoList) {
            if ($wiki === false) {
                $this->TodoList = InOutput::ReadFile(Main::$PathToToDOList);
            } else {
                $helpList = InOutput::ReadWiki(Main::$TodoListWiki);
                foreach ($helpList as $line) {
                    if (($line !== "") or (trim($line) !== "")) {
                        $this->TodoList[] = $line;
                    }
                }
            }
            if (Main::$debug) {
                $this->TodoListDebug = array();
                foreach ($this->TodoList as $item) {
                    $name = substr($item, strpos($item, ' ') + 1);
                    $aufgabe = substr($name, strpos($item, " | ") + 3);
                    $name = substr($name, strpos($name, ' |'));
                    $aufgabe = substr($aufgabe, strpos($aufgabe, ' |'));
                    $this->TodoListDebug[] = "<tr>" . PHP_EOL . "<td>" . $name . "</td>" . PHP_EOL . "<td>" . $aufgabe . "</td>" . PHP_EOL . "</tr>" . PHP_EOL;
                }
            }
        }
        if ($wiki) {
            $this->DecissioList = InOutput::ReadWiki(Main::$decissionListWikiWrite);
            $newDecissions2 = InOutput::ReadWiki(Main::$DecissionListWiki);
            $ToDoList2 = array_diff($newDecissions2, $this->DecissioList);
            $this->DecissioList = array_merge($this->DecissioList, $ToDoList2);
        } else {
            $this->DecissioList = InOutput::ReadFile(Main::$newDecissionList);
        }
    }

    public function processProtokoll($Protokoll, $Legislatur, $fn)
    {
        if (Main::$DatabaseCon->alreadyOnDecissionList($fn)) {
            return;
        }
        $SitzungsNummer = self::crawlSitzungsnummer($Protokoll);
        $this->crawlDecission($Protokoll, $Legislatur, $SitzungsNummer);
        $this->addDecissions($fn, $SitzungsNummer);
        Main::$DatabaseCon->addToDecissionList($fn);
    }

    public static function crawlSitzungsnummer($Protokoll): string
    {
        foreach ($Protokoll as $line) {
            if (strpos($line, "======") !== false) {
                $result = substr($line, strpos($line, "======") + 7);
                $result = substr($result, 0, strpos($result, '.'));
                $result = str_replace(" ", "", $result);
                if (strlen($result) === 1) {
                    $result = "0" . $result;
                }
                return $result;
            }
        }
        return -1;
    }

    private function crawlDecission($Protokoll, $legislatur, $Sitzungsnummer)
    {
        $this->ToDoListDebugHelperAnfang();
        $financialDecissionNumberF = 1;
        $financialDecissionNumberH = 1;
        $DecissionNumber = 1;
        foreach ($Protokoll as $line) {
            if (strpos($line, "TODO") !== false) {
                $todo = substr($line, strpos($line, "TODO") + 4);
                if (strpos($todo, ':')) {
                    $name = $this->removeEmptyBegin(substr($todo, strpos($todo, $line), strpos($todo, ':')));
                    $aufgabe = $this->removeEmptyBegin(substr($todo, strpos($todo, ':') + 1));
                } else {
                    $name = "Everybody";
                    $aufgabe = $this->removeEmptyBegin(substr($todo, strpos($todo, ' ')));
                }
                $this->TodoList[] = $this->getToDoLine($name, $aufgabe);
            }
            if (strpos($line, "template>:vorlagen:stimmen") === false) {
                continue;
            }
            $number = strval($DecissionNumber);
            if (strlen($number) === 1) {
                $number = "0" . $number;
            }
            if ((strpos($line, "beschließt") !== false) and (strpos($line, "Protokoll") !== false) and (strpos($line, "Sitzung") !== false) and (strpos($line, "angenommen") !== false)) {
                $addedLine = "| " . $legislatur . "/" . $Sitzungsnummer . "-" . $number . " | Protokoll | ";
                $text = substr($line, strpos($line, "=") + 1);
                $text = substr($text, 0, strpos($text, "|"));
                $addedLine = $addedLine . $text . "|";
                $this->newDecissions[] = $addedLine . PHP_EOL;
                $DecissionNumber = $DecissionNumber + 1;
            } else if ((strpos($line, "beschließt") !== false) and (strpos($line, "Haushaltsverantwortliche") !== false) and (strpos($line, "Budget") !== false)) {
                $addedLine = "| " . $legislatur . "/" . $Sitzungsnummer . "-H" . $financialDecissionNumberH . " | Finanzen | ";
                $text = substr($line, strpos($line, "=") + 1);
                $text = substr($text, 0, strpos($text, "|"));
                $addedLine = $addedLine . $text . "|";
                $this->newDecissions[] = $addedLine . PHP_EOL;
                $financialDecissionNumberH = $financialDecissionNumberH + 1;
            } else if ((strpos($line, "beschließt") !== false) and (strpos($line, "angenommen") !== false) and (strpos($line, "Budget") !== false)) {
                $addedLine = "| " . $legislatur . "/" . $Sitzungsnummer . "-F" . $financialDecissionNumberF . " | Finanzen | ";
                $text = substr($line, strpos($line, "=") + 1);
                $text = substr($text, 0, strpos($text, "|"));
                $addedLine = $addedLine . $text . " |";
                $this->newDecissions[] = $addedLine . PHP_EOL;
                $financialDecissionNumberF = $financialDecissionNumberF + 1;
            } else if ((strpos($line, "Gründung") !== false) and (strpos($line, "angenommen") !== false)) {
                $addedLine = "| " . $legislatur . "/" . $Sitzungsnummer . "-" . $number . " | Wahl | ";
                $text = substr($line, strpos($line, "=") + 1);
                $text = substr($text, 0, strpos($text, "|"));
                $addedLine = $addedLine . $text . " |";
                $this->newDecissions[] = $addedLine . PHP_EOL;
                $DecissionNumber = $DecissionNumber + 1;
            } else if ((strpos($line, "Auflösung") !== false) and (strpos($line, "angenommen") !== false)) {
                $addedLine = "| " . $legislatur . "/" . $Sitzungsnummer . "-" . $number . " | Wahl | ";
                $text = substr($line, strpos($line, "=") + 1);
                $text = substr($text, 0, strpos($text, "|"));
                $addedLine = $addedLine . $text . " |";
                $this->newDecissions[] = $addedLine . PHP_EOL;
                $DecissionNumber = $DecissionNumber + 1;
            } else if ((strpos($line, "bestätigt") !== false) and (strpos($line, "angenommen") !== false) and (strpos($line, "Amt") !== false)) {
                $addedLine = "| " . $legislatur . "/" . $Sitzungsnummer . "-" . $number . " | Wahl | ";
                $text = substr($line, strpos($line, "=") + 1);
                $text = substr($text, 0, strpos($text, "|"));
                $addedLine = $addedLine . $text . " |";
                $this->newDecissions[] = $addedLine . PHP_EOL;
                $DecissionNumber = $DecissionNumber + 1;
            } else if ((strpos($line, "Leiter") !== false) and (strpos($line, "angenommen") !== false)) {
                $addedLine = "| " . $legislatur . "/" . $Sitzungsnummer . "-" . $number . " | Wahl | ";
                $text = substr($line, strpos($line, "=") + 1);
                $text = substr($text, 0, strpos($text, "|"));
                $addedLine = $addedLine . $text . " |";
                $this->newDecissions[] = $addedLine . PHP_EOL;
                $DecissionNumber = $DecissionNumber + 1;
            } else if ((strpos($line, "Mitglied") !== false) and (strpos($line, "angenommen") !== false)) {
                $addedLine = "| " . $legislatur . "/" . $Sitzungsnummer . "-" . $number . " | Wahl | ";
                $text = substr($line, strpos($line, "=") + 1);
                $text = substr($text, 0, strpos($text, "|"));
                $addedLine = $addedLine . $text . " |";
                $this->newDecissions[] = $addedLine . PHP_EOL;
                $DecissionNumber = $DecissionNumber + 1;
            } else if ((strpos($line, "bestätigt") !== false) and (strpos($line, "angenommen") !== false)) {
                $addedLine = "| " . $legislatur . "/" . $Sitzungsnummer . "-" . $number . " | Wahl | ";
                $text = substr($line, strpos($line, "=") + 1);
                $text = substr($text, 0, strpos($text, "|"));
                $addedLine = $addedLine . $text . " |";
                $this->newDecissions[] = $addedLine . PHP_EOL;
                $DecissionNumber = $DecissionNumber + 1;
            } else if ((strpos($line, "wählt") !== false) and (strpos($line, "angenommen") !== false)) {
                $addedLine = "| " . $legislatur . "/" . $Sitzungsnummer . "-" . $number . " | Wahl | ";
                $text = substr($line, strpos($line, "=") + 1);
                $text = substr($text, 0, strpos($text, "|"));
                $addedLine = $addedLine . $text . " |";
                $this->newDecissions[] = $addedLine . PHP_EOL;
                $DecissionNumber = $DecissionNumber + 1;
            } else if ((strpos($line, "Ordnung") !== false) and (strpos($line, "angenommen") !== false)) {
                $addedLine = "| " . $legislatur . "/" . $Sitzungsnummer . "-" . $number . " | Ordnung | ";
                $text = substr($line, strpos($line, "=") + 1);
                $text = substr($text, 0, strpos($text, "|"));
                $addedLine = $addedLine . $text . " |";
                $this->newDecissions[] = $addedLine . PHP_EOL;
                $DecissionNumber = $DecissionNumber + 1;
            } else if ((strpos($line, "angenommen") !== false)) {
                $addedLine = "| " . $legislatur . "/" . $Sitzungsnummer . "-" . $number . " | Sonstiges | ";
                $text = substr($line, strpos($line, "=") + 1);
                $text = substr($text, 0, strpos($text, "|"));
                $addedLine = $addedLine . $text . " |";
                $this->newDecissions[] = $addedLine . PHP_EOL;
                $DecissionNumber = $DecissionNumber + 1;
            }
        }
        Useroutput::PrintLineDebug("ToDo's");
        foreach ($this->TodoListDebug as $line) {
            Useroutput::PrintLineDebug($line);
        }
        $this->ToDoListDebugHelperEnde();
        Useroutput::PrintLineDebug("ToDo's");
        foreach ($this->TodoList as $line) {
            Useroutput::PrintLineDebug($line);
        }
    }

    private function ToDoListDebugHelperAnfang()
    {
        Useroutput::PrintLineDebug("<table style='border: solid 1px black'>" . PHP_EOL . "<tr>" . PHP_EOL . "<th>Name</th>" . PHP_EOL . "<th>Aufgabe</th>" . PHP_EOL . "</tr>" . PHP_EOL);
    }

    private function removeEmptyBegin($line): string
    {
        $result = $line;
        $check = true;
        do {
            if (substr($result, 0, 1) === " ") {
                $result = substr($result, 1);
            } else {
                $check = false;
            }
        } while ($check);
        return $result;
    }

    private function getToDoLine($name, $aufgabe): string
    {
        $aufgabe = SearchAndRescue::SearchAndReplace($aufgabe);
        $name = SearchAndRescue::SearchAndReplace($name);
        $line = "|  " . $name . " | " . $aufgabe . " |";
        if ($this->wiki === false) {
            $line = $line . PHP_EOL;
        }
        $this->TodoListDebug[] = "<tr>" . PHP_EOL . "<td>" . $name . "</td>" . PHP_EOL . "<td>" . $aufgabe . "</td>" . PHP_EOL . "</tr>" . PHP_EOL;
        return $line;
    }

    private function ToDoListDebugHelperEnde()
    {
        Useroutput::PrintLineDebug("</table>");
    }

    private function addDecissions($fn, $SitzungsNumer)
    {
        $result = array();
        foreach ($this->DecissioList as $line) {
            $result[] = $line;
        }
        $result[] = "^ Woche " . $SitzungsNumer . " vom [[" . Main::$restDecissionListTitel . $fn . "]]   ^^^" . PHP_EOL;
        foreach ($this->newDecissions as $line2) {
            $result[] = $line2;
        }
        InOutput::WriteFile(Main::$newDecissionList, $result);
        if (Main::$enableToDoList) {
            Useroutput::PrintLineDebug("Writing ToDo-List");
            InOutput::WriteFile(Main::$PathToToDOList, $this->TodoList);
        }
    }

    public function processProtokollWiki($Protokoll, $Legislatur, $fn)
    {
        if (Main::$DatabaseCon->alreadyOnDecissionList($fn)) {
            return;
        }
        $SitzungsNummer = self::crawlSitzungsnummer($Protokoll);
        $this->crawlDecission($Protokoll, $Legislatur, $SitzungsNummer);
        $this->addDecissionsWiki($fn, $SitzungsNummer);
        Main::$DatabaseCon->addToDecissionList($fn);
    }

    private function addDecissionsWiki($fn, $SitzungsNumer)
    {
        $result = array();
        foreach ($this->DecissioList as $line) {
            $result[] = $line;
        }
        $result[] = "^ Woche " . $SitzungsNumer . " vom [[" . Main::$restDecissionListTitel . $fn . "]]   ^^^";
        foreach ($this->newDecissions as $line2) {
            $result[] = $line2;
        }
        InOutput::WriteWiki(Main::$decissionListWikiWrite, $result);
        if (Main::$enableToDoList) {
            Useroutput::PrintLineDebug("Writing ToDo-List");
            InOutput::WriteWiki(Main::$TodoListWiki, $this->TodoList);
        }
    }
}
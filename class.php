<?

class FBiBlock {

	// кол-во элементов в запросе к бд (оптимальное - 1000), номер инфоблока, данные сессии
	public static $batch, $iblock, $session = [];

    // очистка инфоблока по частям = кол-во элементов в запросе к бд
    public static function DeletePart($files = true) {
        $iblock = SELF::$iblock;
        $batch = SELF::$batch;
		$version = CIBlockElement::GetIBVersion($iblock);

		// пока только делаем для версии 1
		if ($iblock and $version == 1) {

			// собираем свойства элементов с файлами
			if ($files) $arrProps = FBiBlock::GetFileProps();
            $arrFiles = [];

            // обработка элементов + сбор картинки и файлы
			$elements = CIBlockElement::GetList([], ["IBLOCK_ID" => $iblock], false, ["nTopCount" => $batch], ['ID']);
			$arrID = [];
			while ( $element = $elements->Fetch() ) $arrID[] = ($element['ID']);
            if ($files) {
                $arrFiles = array_merge($arrFiles, FBiBlock::GetElementsPics($arrID));
                $arrFiles = array_merge($arrFiles, FBiBlock::GetFiles($arrID, $arrProps));
            }
            FBiBlock::DeleteElements($arrID);
            SELF::$session["elements"] = count($arrID);

            // обработка каталогов + сбор картинки
            $elements = CIBlockSection::GetList([], ['IBLOCK_ID' => $iblock], false, ['ID'], ["nTopCount" => $batch]);
			$arrID = [];
			while ( $element = $elements->Fetch() ) $arrID[] = ($element['ID']);
            if ($files) $arrFiles = array_merge($arrFiles, FBiBlock::GetSectionPics($arrID));
            FBiBlock::DeleteSections($arrID);
            SELF::$session["sections"] = count($arrID);

            // удаляем все полученные картинки и файлы
            $total = count($arrFiles);
            $count = 0;
            $arrID = [];
            while ($files and $count < $total) {
                $arrID[] = ($arrFiles[$count]);
                $count += 1;
                if ($count % $batch == 0 or $count == $total) {
                    FBiBlock::DeleteFiles($arrID);
                    $arrID = [];
                }
            }
            SELF::$session["files"] = $total;

            return SELF::$session;
        }
    }

    // полная очистка инфоблока разом
	public static function DeleteAll($files = true) {

        $iblock = SELF::$iblock;
		$version = CIBlockElement::GetIBVersion($iblock);

		// пока только делаем для версии 1
		if ($iblock and $version == 1) {

			// собираем все картинки и всех элементов и каталогов инфоблока + свойства элементов с файлами
			if ($files) {
				$arrFiles = FBiBlock::GetElementsPics();
				$arrFiles = array_merge($arrFiles, FBiBlock::GetSectionPics());
				$arrProps = FBiBlock::GetFileProps();
			}

			// обработка элементов
			$time_start = microtime(true);

			$elements = CIBlockElement::GetList([], ["IBLOCK_ID" => $iblock], false, false, ['ID']);
			$total = CIBlock::GetElementCount($iblock);
			$count = 0;
			$arrID = [];
			while ( $element = $elements->Fetch() ) {
				$arrID[] = ($element['ID']);
				$count += 1;
				if ($count % SELF::$batch == 0 or $count == $total) {
					if ($files) 
                        $arrFiles = array_merge($arrFiles, FBiBlock::GetFiles($arrID, $arrProps));
					FBiBlock::DeleteElements($arrID);
					$arrID = [];
				}
			}
			$time_end = microtime(true);
			$execution_time = round(($time_end - $time_start), 2);
			if ($count) echo "<br>удалены элементы - $count; обработка: $execution_time сек. <br>";

			// обработка каталогов
			$time_start = microtime(true);

			$elements = CIBlockSection::GetList([], ['IBLOCK_ID' => $iblock], false, ['ID']);
			$total = CIBlockSection::GetCount(['IBLOCK_ID' => $iblock]);
			$count = 0;
			$arrID = [];
			while ( $element = $elements->Fetch() ) {
				$arrID[] = ($element['ID']);
				$count += 1;
				if ($count % SELF::$batch == 0 or $count == $total) {
					FBiBlock::DeleteSections($arrID);
					$arrID = [];
				}
			}
			$time_end = microtime(true);
			$execution_time = round(($time_end - $time_start), 2);
			if ($count) echo "<br>удалены каталоги - $count; обработка: $execution_time сек. <br>";

			// удаляем все картинки и файлы
			if ($files) {
				$time_start = microtime(true);

				$total = count($arrFiles);
				$count = 0;
				$arrID = [];
				while ($count < $total) {
					$arrID[] = ($arrFiles[$count]);
					$count += 1;
					if ($count % SELF::$batch == 0 or $count == $total) {
						FBiBlock::DeleteFiles($arrID);
						$arrID = [];
					}
				}

				$time_end = microtime(true);
				$execution_time = round(($time_end - $time_start), 2);
				if ($count) echo "<br>удалены файлы - $count; обработка: $execution_time сек. <br>";
			}

			return true;
		}
		else return false;
	}

	// создание элементов в бд
	public static function Create($total, $files = true) {
		global $DB;
        $iblock = SELF::$iblock;

		$count = 0;
		$arrID = [];
		while ( $count < $total ) {
			$arrID[] = "($count, $iblock)";
			$count += 1;
			if ($count % SELF::$batch == 0 or $count == $total) {
				$sub = implode(',', $arrID);
				$DB->Query("INSERT INTO b_iblock_element (NAME, IBLOCK_ID) VALUES ".$sub);
				$arrID = [];
			}
		}
		echo "<br>созданы элементы - $count <br>";
	}

	// получение картинок (анонс и подробно) для элементов 
	public static function GetElementsPics($arrID = NULL) {
		global $DB;
        $iblock = SELF::$iblock;

		$values = [];
        $sub = implode(',', $arrID);
        if ($arrID) $query = $DB->Query("SELECT PREVIEW_PICTURE, DETAIL_PICTURE 
            FROM b_iblock_element WHERE ID IN (".$sub.")");
		elseif ($arrID == NULL) $query = $DB->Query("SELECT PREVIEW_PICTURE, DETAIL_PICTURE 
            FROM b_iblock_element WHERE IBLOCK_ID = $iblock");
		while ($query and $props = $query->Fetch()) {
			if ($props['PREVIEW_PICTURE']) $values[] = $props['PREVIEW_PICTURE'];
			if ($props['DETAIL_PICTURE']) $values[] = $props['DETAIL_PICTURE'];
		}
		return $values;
	}

	// полученине картинок (анонс и подробно) для каталогов 
	public static function GetSectionPics($arrID = NULL) {
		global $DB;
        $iblock = SELF::$iblock;

		$values = [];
        $sub = implode(',', $arrID);
        if ($arrID) $query = $DB->Query("SELECT PICTURE, DETAIL_PICTURE 
            FROM b_iblock_section WHERE ID IN (".$sub.")");
        elseif ($arrID == NULL) $query = $DB->Query("SELECT PICTURE, DETAIL_PICTURE 
            FROM b_iblock_section WHERE IBLOCK_ID = $iblock");
		while ($query and $props = $query->Fetch()) {
			if ($props['PICTURE']) $values[] = $props['PICTURE'];
			if ($props['DETAIL_PICTURE']) $values[] = $props['DETAIL_PICTURE'];
		}
		return $values;
	}

	// полученине свойств элементов с файлами
	public static function GetFileProps() {
		global $DB;
        $iblock = SELF::$iblock;

		$values = [];
		$query = $DB->Query("SELECT ID FROM b_iblock_property where IBLOCK_ID=$iblock and PROPERTY_TYPE='F'");
		while ($props = $query->Fetch()) $values[] = $props['ID'];
		return $values;
	}

	// полученине всех файлов во всех свойствах элементов
	public static function GetFiles($arrID, $arrProps) {
		global $DB;

		$values = [];
		if ($arrID and $arrProps) {
			$sub_el = implode(',', $arrID);
			$sub_pr = implode(',', $arrProps);
			$query = $DB->Query("SELECT VALUE FROM b_iblock_element_property WHERE 
				IBLOCK_ELEMENT_ID IN (".$sub_el.") AND IBLOCK_PROPERTY_ID IN (".$sub_pr.")");
			while ($props = $query->Fetch()) $values[] = $props['VALUE'];
		}
		return $values;
	}

	// удаление элементов из бд (с оптимизацией если по порядку)
	public static function DeleteElements($arrID) {
		global $DB;

        if ($arrID) {
            $diff = count($arrID) - 1;
            $first = $arrID[0];
            $last = end($arrID);
            $sub = implode(',', $arrID);
            if ($diff == intval($last) - intval($first)) {
                $DB->Query("DELETE FROM b_iblock_element_property WHERE IBLOCK_ELEMENT_ID BETWEEN ".$first." AND ".$last);
                $DB->Query("DELETE FROM b_iblock_section_element WHERE IBLOCK_ELEMENT_ID BETWEEN ".$first." AND ".$last);
                $DB->Query("DELETE FROM b_iblock_element WHERE ID BETWEEN ".$first." AND ".$last);
            }
            else {
                $DB->Query("DELETE FROM b_iblock_element_property WHERE IBLOCK_ELEMENT_ID in (".$sub.")");
                $DB->Query("DELETE FROM b_iblock_section_element WHERE IBLOCK_ELEMENT_ID in (".$sub.")");
                $DB->Query("DELETE FROM b_iblock_element WHERE ID in (".$sub.")");
            }
        }
	}

	// удаление каталогов из бд
	public static function DeleteSections($arrID) {
		global $DB;

        if ($arrID)  {
            $sub = implode(',', $arrID);
            $DB->Query("DELETE FROM b_iblock_section_property WHERE SECTION_ID in (".$sub.")");
            $DB->Query("DELETE FROM b_iblock_section_element WHERE IBLOCK_SECTION_ID in (".$sub.")");
            $DB->Query("DELETE FROM b_iblock_section WHERE ID in (".$sub.")");
        }
	}

	// удаление файлов с диска и из бд (с оптимизацией если по порядку)
	public static function DeleteFiles($arrID) {
		global $DB;

        if ($arrID) {
            $diff = count($arrID) - 1;
            $first = $arrID[0];
            $last = end($arrID);
            $sub = implode(',', $arrID);
            $upload_dir = COption::GetOptionString("main", "upload_dir", "upload");

            if ($diff == intval($last) - intval($first)) {
                $query = $DB->Query("SELECT FILE_NAME, SUBDIR FROM b_file WHERE ID BETWEEN ".$first." AND ".$last);
                $DB->Query("DELETE FROM b_file WHERE ID BETWEEN ".$first." AND ".$last);
            }
            else {
                $query = $DB->Query("SELECT FILE_NAME, SUBDIR FROM b_file WHERE ID IN (".$sub.")");
                $DB->Query("DELETE FROM b_file WHERE ID IN (".$sub.")");
            }

            while ($props = $query->Fetch()) {
                $dname = $_SERVER["DOCUMENT_ROOT"]."/".$upload_dir."/".$props["SUBDIR"];
                $fname = $dname."/".$props["FILE_NAME"];
                $io = CBXVirtualIo::GetInstance();
                $file = $io->GetFile($fname);
                if ($file->isExists()) $file->unlink();
                $directory = $io->GetDirectory($dname);
                if($directory->isExists() && $directory->isEmpty()) $directory->rmdir();
            }
        }
	}

}
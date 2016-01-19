<?php
class report_management extends MY_Controller {

	var $counter = 0;
	var $drug_array = array();
	var $facility_commodity_row = "";
	var $commodity_details = array();
	var $com_summary = array();
	var $count_rows = 0;
	function __construct() {
		parent::__construct();
		ini_set("max_execution_time", "1000000");
		ini_set("memory_limit", '2048M');
		
		$this -> load -> library(array('mpdf'));
		$this -> load -> helper(array("file","download"));
	}

	public function index() {
		$ccc_stores = CCC_store_service_point::getAllActive();
		$this -> session -> set_userdata('ccc_store',$ccc_stores);
		$this -> listing();
	}

	public function getMOHForm($type = "711", $period_start = "", $period_end) {
		$this -> load -> library('PHPExcel');
		$dir = "Export";
		if ($type == "711") {
			$template = "711_template";
		} else if ($type == "731") {
			$template = "731_template";
		}
		$inputFileType = 'Excel5';
		$inputFileName = $_SERVER['DOCUMENT_ROOT'] . '/ADT/assets/' . $template . '.xls';
		$objReader = PHPExcel_IOFactory::createReader($inputFileType);
		$objPHPExcel = $objReader -> load($inputFileName);

		/*Delete all files in export folder*/
		if (is_dir($dir)) {
			$files = scandir($dir);
			foreach ($files as $object) {
				if ($object != "." && $object != "..") {
					unlink($dir . "/" . $object);
				}
			}
		} else {
			mkdir($dir);
		}

		//Facility Info
		$year = date('Y', strtotime($period_start));
		$facility = Facilities::getCodeFacility($this -> session -> userdata("facility"));

		if ($type == "711") {
			$month = date('F', strtotime($period_start));
			$objPHPExcel -> getActiveSheet() -> SetCellValue('C7', $facility -> name);
			$objPHPExcel -> getActiveSheet() -> SetCellValue('A9', $facility -> facilitycode);
			$objPHPExcel -> getActiveSheet() -> SetCellValue('C9', $facility -> Parent_District -> Name);
			$objPHPExcel -> getActiveSheet() -> SetCellValue('H9', $month);
			$objPHPExcel -> getActiveSheet() -> SetCellValue('J9', $year);

			$data_array = $this -> get_711($period_start);
		} else if ($type == "731") {
			$month = date('M', strtotime($period_start));
			$objPHPExcel -> getActiveSheet() -> SetCellValue('B3', $facility -> name);
			$objPHPExcel -> getActiveSheet() -> SetCellValue('M3', $facility -> facilitycode);
			$objPHPExcel -> getActiveSheet() -> SetCellValue('G3', $facility -> Parent_District -> Name);
			$objPHPExcel -> getActiveSheet() -> SetCellValue('I3', $month);
			$objPHPExcel -> getActiveSheet() -> SetCellValue('K3', $year);

			$data_array = $this -> get_731($period_start);
		}

		foreach ($data_array as $mydata) {
			foreach ($mydata as $column => $value) {
				$objPHPExcel -> getActiveSheet() -> SetCellValue($column, $value);
			}
		}

		//Generate file
		ob_start();
		$period_start = date("F-Y", strtotime($period_start));
		$original_filename = "MOH " . $type . " form for (" . $period_start . ").xls";
		$filename = $dir . "/" . urldecode($original_filename);
		$objWriter = new PHPExcel_Writer_Excel5($objPHPExcel);
		$objWriter -> save($filename);
		$objPHPExcel -> disconnectWorksheets();
		unset($objPHPExcel);
		if (file_exists($filename)) {
			$filename = str_replace("#", "%23", $filename);
			redirect($filename);
		}

	}

	public function get_711($period = "") {
		$moh_711 = array();
		$moh_711[] = $this -> on_family_planning($period);
		$moh_711[] = $this -> art_enrolled($period);
		$moh_711[] = $this -> cumulative_enrolled_at_facility($period);
		$moh_711[] = $this -> receiving_tb_treatment($period);
		$moh_711[] = $this -> who_stages($period);
		$moh_711[] = $this -> cumulative_art_at_facility($period);
		$moh_711[] = $this -> pregnant_on_arv($period);
		$moh_711[] = $this -> total_on_arv($period);
		$moh_711[] = $this -> eligible_but_not_on_arv($period);
		$moh_711[] = $this -> post_exposure_prophylaxis($period);
		$moh_711[] = $this -> on_prophylaxis($period);

		return $moh_711;
	}

	public function on_family_planning($period = "March-2014") {
		$period_start = date('Y-m-01', strtotime($period));
		$period_end = date('Y-m-t', strtotime($period));

		$family = array();
		$family['Microlut'] = 0;
		$family['Microgynon'] = 0;
		$family['Injection'] = 0;
		$family['IUCD'] = 0;
		$family['Implants'] = 0;
		$family['BTL'] = 0;
		$family['Vasectomy'] = 0;
		$family['Condoms'] = 0;
		$family['Others'] = 0;

		//new clients on family planning
		$sql = "SELECT p.fplan
			    FROM patient p
			    LEFT JOIN gender g ON g.id=p.gender
			    WHERE date_enrolled
			    BETWEEN '$period_start'
			    AND '$period_end'
			    AND p.fplan !='' 
			    AND p.fplan !='null'
			    AND p.active='1'
			    AND g.name LIKE '%female%'";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();

		if ($results) {
			foreach ($results as $result) {
				if (strstr($result['fplan'], ',', true)) {
					$values = explode(",", $result['fplan']);
					foreach ($values as $value) {
						$arr[] = $value;
					}
				} else {
					$arr[] = $result['fplan'];
				}
			}

			$family_planning = array_count_values($arr);

			foreach ($family_planning as $family_plan => $index) {
				$sql = "select name from family_planning where indicator='$family_plan'";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				if ($results) {
					foreach ($results as $result) {
						$counter = 0;
						if (stripos($result['name'], "levonorgestrel 0.03mg") !== FALSE) {
							$family['Microlut'] = $index;
							$counter = 1;
						}
						if (stripos($result['name'], "Combined Oral Contraception(Levonorgestrel/ethinylestradiol 0.15/0.03mg)") !== FALSE) {
							$family['Microgynon'] = $index;
							$counter = 1;
						}
						if (stripos($result['name'], "Medroxyprogestrone 150 mg") !== FALSE) {
							$family['Injection'] = $index;
							$counter = 1;
						}
						if (stripos($result['name'], "Intrauterine Contraceptive Device(copper T)") !== FALSE) {
							$family['IUCD'] = $index;
							$counter = 1;
						}
						if (stripos($result['name'], "Implants(levonorgestrel 75mg)") !== FALSE) {
							$family['Implants'] = $index;
							$counter = 1;
						}
						if (stripos($result['name'], "Tubaligation") !== FALSE) {
							$family['BTL'] = $index;
							$counter = 1;
						}
						if (stripos($result['name'], "Vasectomy") !== FALSE) {
							$family['Vasectomy'] = $index;
							$counter = 1;
						}
						if (stripos($result['name'], "Condoms") !== FALSE) {
							$family['Condoms'] = $index;
							$counter = 1;
						}
						if ($counter == 0) {
							$family['Others'] = $index;
						}
					}
				}
			}
		}
		$data['E12'] = $family['Microlut'];
		$data['E13'] = $family['Microgynon'];
		$data['E14'] = $family['Injection'];
		$data['E15'] = $family['IUCD'];
		$data['E16'] = $family['Implants'];
		$data['E17'] = $family['BTL'];
		$data['E18'] = $family['Vasectomy'];
		$data['E19'] = $family['Condoms'];
		$data['E20'] = $family['Others'];

		$family = array();
		$family['Microlut'] = 0;
		$family['Microgynon'] = 0;
		$family['Injection'] = 0;
		$family['IUCD'] = 0;
		$family['Implants'] = 0;
		$family['BTL'] = 0;
		$family['Vasectomy'] = 0;
		$family['Condoms'] = 0;
		$family['Others'] = 0;

		//revisits clients on family planning
		$sql = "SELECT p.fplan
			    FROM patient p
			    LEFT JOIN gender g ON g.id=p.gender
			    WHERE date_enrolled <='$period_start'
			    AND p.fplan !='' 
			    AND p.fplan !='null'
			    AND p.active='1'
			    AND g.name LIKE '%female%'";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();

		if ($results) {
			foreach ($results as $result) {
				if (strstr($result['fplan'], ',', true)) {
					$values = explode(",", $result['fplan']);
					foreach ($values as $value) {
						$arr[] = $value;
					}
				} else {
					$arr[] = $result['fplan'];
				}
			}

			$family_planning = array_count_values($arr);

			foreach ($family_planning as $family_plan => $index) {
				$sql = "select name from family_planning where indicator='$family_plan'";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				if ($results) {
					foreach ($results as $result) {
						$counter = 0;
						if (stripos($result['name'], "levonorgestrel 0.03mg") !== FALSE) {
							$family['Microlut'] = $index;
							$counter = 1;
						}
						if (stripos($result['name'], "Combined Oral Contraception(Levonorgestrel/ethinylestradiol 0.15/0.03mg)") !== FALSE) {
							$family['Microgynon'] = $index;
							$counter = 1;
						}
						if (stripos($result['name'], "Medroxyprogestrone 150 mg") !== FALSE) {
							$family['Injection'] = $index;
							$counter = 1;
						}
						if (stripos($result['name'], "Intrauterine Contraceptive Device(copper T)") !== FALSE) {
							$family['IUCD'] = $index;
							$counter = 1;
						}
						if (stripos($result['name'], "Implants(levonorgestrel 75mg)") !== FALSE) {
							$family['Implants'] = $index;
							$counter = 1;
						}
						if (stripos($result['name'], "Tubaligation") !== FALSE) {
							$family['BTL'] = $index;
							$counter = 1;
						}
						if (stripos($result['name'], "Vasectomy") !== FALSE) {
							$family['Vasectomy'] = $index;
							$counter = 1;
						}
						if (stripos($result['name'], "Condoms") !== FALSE) {
							$family['Condoms'] = $index;
							$counter = 1;
						}
						if ($counter == 0) {
							$family['Others'] = $index;
						}
					}
				}
			}
		}

		$data['G12'] = $family['Microlut'];
		$data['G13'] = $family['Microgynon'];
		$data['G14'] = $family['Injection'];
		$data['G15'] = $family['IUCD'];
		$data['G16'] = $family['Implants'];
		$data['G17'] = $family['BTL'];
		$data['G18'] = $family['Vasectomy'];
		$data['G19'] = $family['Condoms'];
		$data['G20'] = $family['Others'];

		$data['C20'] = "Emergency Contraceptive pills(levonorgestrel0.75 mg)";

		return $data;
	}

	public function art_enrolled($period = "") {
		$period_start = date('Y-m-01', strtotime($period));
		$period_end = date('Y-m-t', strtotime($period));
		$counter = 0;
		$total = 0;
		$main = array();
		$patient_group = array("child_female", "child_male", "adult_female", "adult_male");

		foreach ($patient_group as $patient) {
			$family['PMCT'] = 0;
			$family['VCT'] = 0;
			$family['TB'] = 0;
			$family['In patients'] = 0;
			$family['CWC'] = 0;
			$family['Others'] = 0;
			//get new enrolled patients map to source
			$sources = Patient::getEnrollment($period_start, $period_end, $patient);
			foreach ($sources as $source) {
				if (stripos($source['source_name'], "PMCT") !== FALSE) {
					$family['PMCT'] = $source['total'];
					$counter = 1;
				}
				if (stripos($source['source_name'], "HTC") !== FALSE) {
					$family['VCT'] = $source['total'];
					$counter = 1;
				}
				if (stripos($source['source_name'], "TB") !== FALSE) {
					$family['TB'] = $source['total'];
					$counter = 1;
				}
				if (stripos($source['source_name'], "In patient") !== FALSE) {
					$family['In patients'] = $source['total'];
					$counter = 1;
				}
				if (stripos($source['source_name'], "CWC") !== FALSE) {
					$family['CWC'] = $source['total'];
					$counter = 1;
				}
				if ($counter == 0) {
					$total += $source['total'];
					$family['Others'] = $total;
				}
			}
			$main[$patient] = $family;
			unset($family);
			$total = 0;
		}

		foreach ($main as $type => $mydata) {
			if ($type == "child_female") {
				$column = "D";
			} else if ($type == "child_male") {
				$column = "E";
			} else if ($type == "adult_female") {
				$column = "F";
			} else if ($type == "adult_male") {
				$column = "G";
			}
			foreach ($mydata as $index => $value) {
				if ($index == "PMCT") {
					$row = "148";
				} else if ($index == "VCT") {
					$row = "149";
				} else if ($index == "TB") {
					$row = "150";
				} else if ($index == "In patients") {
					$row = "151";
				} else if ($index == "CWC") {
					$row = "152";
				} else if ($index == "Others") {
					$row = "153";
				}
				$data[$column . $row] = $value;
			}
		}

		return $data;
	}

	public function cumulative_enrolled_at_facility($period = "") {
		$to = date('Y-m-t', strtotime($period));

		$male_below_fifteen_years = 0;
		$female_below_fifteen_years = 0;
		$male_above_fifteen_years = 0;
		$female_above_fifteen_years = 0;

		$sql = "SELECT DATEDIFF('$to',p.dob) as age,g.name as gender,rst.name as service
				FROM patient p 
				LEFT JOIN gender g ON g.id=p.gender
				LEFT JOIN regimen_service_type rst ON rst.id=p.service
				WHERE p.date_enrolled <='$to'
				AND(rst.name LIKE '%art%' OR rst.name LIKE '%oi%' OR rst.name LIKE '%pmtct%')
		        AND p.active='1'
		        GROUP BY p.id";

		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				if (strtolower($result['gender']) == "female" && $result['age'] >= (365 * 15)) {
					$female_above_fifteen_years++;
				} else if (strtolower($result['gender']) == "male" && $result['age'] >= (365 * 15) && strtolower($result['service']) != "pmtct") {
					$male_above_fifteen_years++;
				} else if (strtolower($result['gender']) == "female" && $result['age'] < (365 * 15) && strtolower($result['service']) != "pmtct") {
					$female_below_fifteen_years++;
				} else if (strtolower($result['gender']) == "male" && $result['age'] < (365 * 15) && strtolower($result['service']) != "pmtct") {
					$male_below_fifteen_years++;
				}
			}
		}
		$data = array();
		$data['E155'] = $male_below_fifteen_years;
		$data['D155'] = $female_below_fifteen_years;
		$data['G155'] = $male_above_fifteen_years;
		$data['F155'] = $female_above_fifteen_years;
		return $data;
	}

	public function receiving_tb_treatment($period = "") {
		$to = date('Y-m-t', strtotime($period));

		$male_below_fifteen_years = 0;
		$female_below_fifteen_years = 0;
		$male_above_fifteen_years = 0;
		$female_above_fifteen_years = 0;

		$sql = "SELECT DATEDIFF('$to',p.dob) as age,g.name as gender,rst.name as service
				FROM patient p 
				LEFT JOIN gender g ON g.id=p.gender
				LEFT JOIN regimen_service_type rst ON rst.id=p.service
				WHERE p.date_enrolled <='$to'
				AND p.tb='1'
		        AND p.active='1'
		        GROUP BY p.id";

		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				if (strtolower($result['gender']) == "female" && $result['age'] >= (365 * 15)) {
					$female_above_fifteen_years++;
				} else if (strtolower($result['gender']) == "male" && $result['age'] >= (365 * 15)) {
					$male_above_fifteen_years++;
				} else if (strtolower($result['gender']) == "female" && $result['age'] < (365 * 15)) {
					$female_below_fifteen_years++;
				} else if (strtolower($result['gender']) == "male" && $result['age'] < (365 * 15)) {
					$male_below_fifteen_years++;
				}
			}
		}
		$data = array();
		$data['E156'] = $male_below_fifteen_years;
		$data['D156'] = $female_below_fifteen_years;
		$data['G156'] = $male_above_fifteen_years;
		$data['F156'] = $female_above_fifteen_years;
		return $data;
	}

	public function who_stages($period = "") {
		$period_start = date('Y-m-01', strtotime($period));
		$period_end = date('Y-m-t', strtotime($period));
		$counter = 0;
		$total = 0;
		$main = array();
		$patient_group = array("child_female", "child_male", "adult_female", "adult_male");

		foreach ($patient_group as $patient) {
			$family['stage_1'] = 0;
			$family['stage_2'] = 0;
			$family['stage_3'] = 0;
			$family['stage_4'] = 0;
			//get new enrolled patients map to with WHO stage
			$stages = Patient::getStages($period_start, $period_end, $patient);
			foreach ($stages as $stage) {
				if (stripos($stage['stage_name'], "stage 1") !== FALSE) {
					$family['stage_1'] = $stage['total'];
					$counter = 1;
				}
				if (stripos($stage['stage_name'], "stage 2") !== FALSE) {
					$family['stage_2'] = $stage['total'];
					$counter = 1;
				}
				if (stripos($stage['stage_name'], "stage 3") !== FALSE) {
					$family['stage_3'] = $stage['total'];
					$counter = 1;
				}
				if (stripos($stage['stage_name'], "stage 4") !== FALSE) {
					$family['stage_4'] = $stage['total'];
					$counter = 1;
				}
				$main[$patient] = $family;
				unset($family);
				$total = 0;
			}
		}

		foreach ($main as $type => $mydata) {
			if ($type == "child_female") {
				$column = "D";
			} else if ($type == "child_male") {
				$column = "E";
			} else if ($type == "adult_female") {
				$column = "F";
			} else if ($type == "adult_male") {
				$column = "G";
			}
			foreach ($mydata as $index => $value) {
				if ($index == "stage_1") {
					$row = "157";
				} else if ($index == "stage_2") {
					$row = "158";
				} else if ($index == "stage_3") {
					$row = "159";
				} else if ($index == "stage_4") {
					$row = "160";
				}
				$data[$column . $row] = $value;
			}
		}

		return $data;
	}

	public function cumulative_art_at_facility($period = "") {
		$period_end = date('Y-m-t', strtotime($period));

		$female_adult = 0;
		$male_adult = 0;
		$female_child = 0;
		$male_child = 0;

		$sql = "SELECT DATEDIFF('$period_end',p.dob) as age,g.name as gender
		        FROM patient p
		        LEFT JOIN gender g ON g.id=p.gender
		        LEFT JOIN regimen_service_type rst ON rst.id=p.service
		        WHERE p.start_regimen_date <='$period_end'
		        AND rst.name LIKe '%art%'
		        AND p.active='1'
		        GROUP BY p.id";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				if (strtolower($result['gender']) == "female" && $result['age'] >= (365 * 15)) {
					$female_adult++;
				} else if (strtolower($result['gender']) == "male" && $result['age'] >= (365 * 15)) {
					$male_adult++;
				} else if (strtolower($result['gender']) == "female" && $result['age'] < (365 * 15)) {
					$female_child++;
				} else if (strtolower($result['gender']) == "male" && $result['age'] < (365 * 15)) {
					$male_child++;
				}
			}
		}
		$data['F162'] = $female_adult;
		$data['G162'] = $male_adult;
		$data['D162'] = $female_child;
		$data['E162'] = $male_child;

		return $data;
	}

	public function pregnant_on_arv($period_end = "") {
		$period_end = date('Y-m-t', strtotime($period_end));
		$value = 0;
		$total = 0;
		$main = array();
		$patient_group = array("D163", "F163");
		foreach ($patient_group as $patient) {
			$family['D163'] = 0;
			$family['F163'] = 0;
			$stages = Patient::getPregnant($period_end, $patient);
			foreach ($stages as $stage) {
				$value = $stage['total'];
			}
			$family[$patient] = $value;
		}

		return $family;
	}

	public function total_on_arv($period_end = "") {
		$period_end = date('Y-m-t', strtotime($period_end));
		$value = 0;
		$total = 0;
		$main = array();
		$patient_group = array("D164", "E164", "F164", "G164");
		$family['D164'] = 0;
		$family['E164'] = 0;
		$family['F164'] = 0;
		$family['G164'] = 0;

		foreach ($patient_group as $patient) {
			$stages = Patient::getAllArv($period_end, $patient);
			foreach ($stages as $stage) {
				$value = $stage['total'];
			}
			$family[$patient] = $value;
		}
		return $family;
	}

	public function eligible_but_not_on_arv($period = "") {
		$period_start = date('Y-m-01', strtotime($period));
		$period_end = date('Y-m-t', strtotime($period));

		$female_adult = 0;
		$male_adult = 0;
		$female_child = 0;
		$male_child = 0;

		//get those enrolled
		$sql = "SELECT DATEDIFF('$period_end',p.dob) as age,g.name as gender
		        FROM patient p
		        LEFT JOIN gender g ON g.id=p.gender
		        LEFT JOIN regimen_service_type rst ON rst.id=p.service
		        WHERE p.date_enrolled 
		        BETWEEN '$period_start'
		        AND '$period_end'
		        AND rst.name NOT LIKE '%art%'
		        AND p.active='1'
		        GROUP BY p.id";

		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				if (strtolower($result['gender']) == "female" && $result['age'] >= (365 * 15)) {
					$female_adult++;
				} else if (strtolower($result['gender']) == "male" && $result['age'] >= (365 * 15)) {
					$male_adult++;
				} else if (strtolower($result['gender']) == "female" && $result['age'] < (365 * 15)) {
					$female_child++;
				} else if (strtolower($result['gender']) == "male" && $result['age'] < (365 * 15)) {
					$male_child++;
				}
			}
		}

		$data['F166'] = $female_adult;
		$data['G166'] = $male_adult;
		$data['D166'] = $female_child;
		$data['E166'] = $male_child;

		return $data;
	}

	public function post_exposure_prophylaxis($period = "") {
		$period_start = date('Y-m-01', strtotime($period));
		$period_end = date('Y-m-t', strtotime($period));

		$occassional_male = 0;
		$sexual_assualt_male = 0;
		$other_reason_male = 0;
		$occassional_female = 0;
		$sexual_assualt_female = 0;
		$other_reason_female = 0;

		$occassional_male_child = 0;
		$sexual_assualt_male_child = 0;
		$other_reason_male_child = 0;
		$occassional_female_child = 0;
		$sexual_assualt_female_child = 0;
		$other_reason_female_child = 0;

		$sql = "SELECT DATEDIFF('$period_end',p.dob) as age,pr.name,g.name as gender
                FROM patient p
                LEFT JOIN gender g ON g.id=p.gender
                LEFT JOIN pep_reason pr ON pr.id=p.pep_reason
                LEFT JOIN regimen_service_type rst ON rst.id=p.service
                WHERE p.date_enrolled 
                BETWEEN '$period_start'
                AND '$period_end'
                AND rst.name LIKE '%pep%'
                GROUP BY p.id";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				if ($result['age'] >= (365 * 15)) {
					if ($result['name'] == "Occupational" && strtolower($result['gender']) == "female") {
						$occassional_female++;
					} else if ($result['name'] == "Sexual assault" && strtolower($result['gender']) == "female") {
						$sexual_assualt_female++;
					} else if (strtolower($result['gender']) == "female") {
						$other_reason_female++;
					} else if ($result['name'] == "Occupational" && strtolower($result['gender']) == "male") {
						$occassional_male++;
					} else if ($result['name'] == "Sexual assault" && strtolower($result['gender']) == "male") {
						$sexual_assualt_male++;
					} else if (strtolower($result['gender']) == "male") {
						$other_reason_male++;
					}
				} else {
					if ($result['name'] == "Occupational" && strtolower($result['gender']) == "female") {
						$occassional_female++;
					} else if ($result['name'] == "Sexual assault" && strtolower($result['gender']) == "female") {
						$sexual_assualt_female_child++;
					} else if (strtolower($result['gender']) == "female") {
						$other_reason_female_child++;
					} else if ($result['name'] == "Occupational" && strtolower($result['gender']) == "male") {
						$occassional_male_child++;
					} else if ($result['name'] == "Sexual assault" && strtolower($result['gender']) == "male") {
						$sexual_assualt_male_child++;
					} else if (strtolower($result['gender']) == "male") {
						$other_reason_male_child++;
					}
				}
			}
		}

		$data['G168'] = $occassional_male;
		$data['G167'] = $sexual_assualt_male;
		$data['G169'] = $other_reason_male;
		$data['F168'] = $occassional_female;
		$data['F167'] = $sexual_assualt_female;
		$data['F169'] = $other_reason_female;

		$data['E168'] = $occassional_male_child;
		$data['E167'] = $sexual_assualt_male_child;
		$data['E169'] = $other_reason_male_child;
		$data['D168'] = $occassional_female_child;
		$data['D167'] = $sexual_assualt_female_child;
		$data['D169'] = $other_reason_female_child;

		return $data;
	}

	public function on_prophylaxis($period = "") {
		$period_start = date('Y-m-01', strtotime($period));
		$period_end = date('Y-m-t', strtotime($period));

		//get propholaxis(cotri)
		$female_adult = 0;
		$male_adult = 0;
		$female_child = 0;
		$male_child = 0;

		$sql = "SELECT DATEDIFF('$period_end',p.dob) as age,g.name as gender
		        FROM patient p
                LEFT JOIN drug_prophylaxis dp ON dp.id=p.drug_prophylaxis
		        LEFT JOIN gender g ON g.id=p.gender
		        LEFT JOIN patient_status ps ON ps.id=p.current_status
		        WHERE p.date_enrolled <='$period_end'
		        AND dp.name LIKE '%cotri%'
		        AND p.active='1'
		        AND ps.Name LIKE '%active%'
		        GROUP BY p.id";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				if (strtolower($result['gender']) == "female" && $result['age'] >= (365 * 15)) {
					$female_adult++;
				} else if (strtolower($result['gender']) == "male" && $result['age'] >= (365 * 15)) {
					$male_adult++;
				} else if (strtolower($result['gender']) == "female" && $result['age'] < (365 * 15)) {
					$female_child++;
				} else if (strtolower($result['gender']) == "male" && $result['age'] < (365 * 15)) {
					$male_child++;
				}
			}
		}

		$data['F171'] = $female_adult;
		$data['G171'] = $male_adult;
		$data['D171'] = $female_child;
		$data['E171'] = $male_child;

		//get propholaxis(fluconazole)
		$female_adult = 0;
		$male_adult = 0;
		$female_child = 0;
		$male_child = 0;

		$sql = "SELECT DATEDIFF('$period_end',p.dob) as age,g.name as gender
		        FROM patient p
                LEFT JOIN drug_prophylaxis dp ON dp.id=p.drug_prophylaxis
		        LEFT JOIN gender g ON g.id=p.gender
		        LEFT JOIN patient_status ps ON ps.id=p.current_status
		        WHERE p.date_enrolled <='$period_end'
		        AND dp.name LIKE '%fluconazole%'
		        AND p.active='1'
		        AND ps.Name LIKE '%active%'
		        GROUP BY p.id";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				if (strtolower($result['gender']) == "female" && $result['age'] >= (365 * 15)) {
					$female_adult++;
				} else if (strtolower($result['gender']) == "male" && $result['age'] >= (365 * 15)) {
					$male_adult++;
				} else if (strtolower($result['gender']) == "female" && $result['age'] < (365 * 15)) {
					$female_child++;
				} else if (strtolower($result['gender']) == "male" && $result['age'] < (365 * 15)) {
					$male_child++;
				}
			}
		}

		$data['F172'] = $female_adult;
		$data['G172'] = $male_adult;
		$data['D172'] = $female_child;
		$data['E172'] = $male_child;

		return $data;
	}

	public function get_731($period = "") {
		$moh_731 = array();
		$moh_731[] = $this -> on_ctx($period);
		$moh_731[] = $this -> enrolled_in_care($period);
		$moh_731[] = $this -> currently_in_care($period);
		$moh_731[] = $this -> all_art($period);
		$moh_731[] = $this -> cumulative_ever_on_art($period);
		$moh_731[] = $this -> survival_and_retention($period);
		$moh_731[] = $this -> screening($period);
		$moh_731[] = $this -> prevention_with_positives($period);
		$moh_731[] = $this -> hiv_care_visits($period);

		$moh_731[] = $this -> type_of_exposure($period);
		$moh_731[] = $this -> provided_with_prophylaxis($period);

		return $moh_731;
	}

	public function on_ctx($period = "March-2014") {
		$period_end = date('Y-m-t', strtotime($period));
		$female_ctx_over_15 = 0;
		$male_ctx_over_15 = 0;
		$female_ctx_below_15 = 0;
		$male_ctx_below_15 = 0;
		$infant_exposed = 0;
		$infant_eligible = 0;

		/*
		 * 1.HIV Exposed Infant(within 2 months)-are those infants below 2 months of age,on PMTCT line and are on ctx drug prophylaxis
		 * 2.HIV Exposed Infant(Eligible for CTX at 2 months)-are those infants below 2 months of age,on PMTCT line and are not on ctx drug prophylaxis
		 * 3.On CTX - Below 15 Years and minimum of 1 year and are on ctx drug prophylaxis
		 * 4.On CTX - 15 years & Older and are on ctx drug prophylaxis only ART,PMTCT-adult only
		 */

		$sql = "SELECT COUNT(*) as total
		        FROM patient p
		        LEFT JOIN gender g ON g.id=p.gender
		        LEFT JOIN regimen_service_type rst ON rst.id=p.service
		        LEFT JOIN drug_prophylaxis dp ON dp.id=p.drug_prophylaxis
		        LEFT JOIN patient_status ps ON ps.id=p.current_status
		        WHERE rst.name LIKE '%pmtct%'
		        AND dp.name LIKE '%cotri%'
		        AND DATEDIFF('$period_end',p.dob) <=60
		        AND p.active='1'
		        AND ps.Name LIkE '%active%'";

		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				$infant_exposed = $result['total'];
			}
		}

		$sql = "SELECT COUNT(*) as total
		        FROM patient p
		        LEFT JOIN gender g ON g.id=p.gender
		        LEFT JOIN regimen_service_type rst ON rst.id=p.service
		        LEFT JOIN drug_prophylaxis dp ON dp.id=p.drug_prophylaxis
		        LEFT JOIN patient_status ps ON ps.id=p.current_status
		        WHERE rst.name LIKE '%pmtct%'
		        AND dp.name NOT LIKE '%cotri%'
		        AND DATEDIFF('$period_end',p.dob) <=60
		        AND p.active='1'
		        AND ps.Name LIkE '%active%'";

		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				$infant_eligible = $result['total'];
			}
		}

		$sql = "SELECT g.name as gender,COUNT(*) as total
		        FROM patient p
		        LEFT JOIN gender g ON g.id=p.gender
		        LEFT JOIN regimen_service_type rst ON rst.id=p.service
		        LEFT JOIN drug_prophylaxis dp ON dp.id=p.drug_prophylaxis
		        LEFT JOIN patient_status ps ON ps.id=p.current_status
		        WHERE(rst.name LIKE '%art%' OR rst.name LIKE '%oi%')
		        AND dp.name LIKE '%cotri%'
		        AND DATEDIFF('$period_end',p.dob) >=365
		        AND DATEDIFF('$period_end',p.dob) <(365*15)
				AND p.active='1'
				AND ps.Name LIkE '%active%'
		        GROUP BY g.name";

		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				if (strtolower($result['gender']) == "female") {
					$female_ctx_below_15 = $result['total'];
				} else if (strtolower($result['gender']) == "male") {
					$male_ctx_below_15 = $result['total'];
				}
			}
		}

		$sql = "SELECT g.name as gender,COUNT(*) as total
		        FROM patient p
		        LEFT JOIN gender g ON g.id=p.gender
		        LEFT JOIN regimen_service_type rst ON rst.id=p.service
		        LEFT JOIN drug_prophylaxis dp ON dp.id=p.drug_prophylaxis
		        LEFT JOIN patient_status ps ON ps.id=p.current_status
		        WHERE(rst.name LIKE '%art%' OR rst.name LIKE '%pmtct%' OR rst.name LIKE '%oi%')
		        AND dp.name LIKE '%cotri%'
		        AND DATEDIFF('$period_end',p.dob) >=(365*15)
				AND p.active='1'
				AND ps.Name LIkE '%active%'
		        GROUP BY g.name";

		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				if (strtolower($result['gender']) == "female") {
					$female_ctx_over_15 = $result['total'];
				} else if (strtolower($result['gender']) == "male") {
					$male_ctx_over_15 = $result['total'];
				}
			}
		}

		$data['J6'] = $infant_exposed;
		$data['J7'] = $infant_eligible;
		$data['J8'] = $male_ctx_below_15;
		$data['L8'] = $female_ctx_below_15;
		$data['J9'] = $male_ctx_over_15;
		$data['L9'] = $female_ctx_over_15;

		return $data;
	}

	public function enrolled_in_care($selected_period = "") {
		//Variables
		$period = explode('-', $selected_period);
		$year = $period[1];
		$month = date('m', strtotime($period[0]));
		$today = date('Y-m-d', strtotime("01-$selected_period"));
		$below_one_year = 0;
		$male_below_fifteen_years = 0;
		$female_below_fifteen_years = 0;
		$male_above_fifteen_years = 0;
		$female_above_fifteen_years = 0;

		//Get patients enrolled in care below 1 year
		$sql = "SELECT COUNT(*) AS total 
		        FROM patient 
		        WHERE MONTH(date_enrolled)='$month' 
		        AND YEAR(date_enrolled)='$year' 
		        AND DATEDIFF('$today',dob)<365 
		        AND active='1'";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			$below_one_year = $results[0]['total'];
		}

		//Get Patients enrolled in care below 15 years
		$sql = "SELECT COUNT(*) AS total,gender 
		        FROM patient 
		        WHERE MONTH(date_enrolled)='$month' 
		        AND YEAR(date_enrolled)='$year' 
		        AND DATEDIFF('$today',dob)>=365 
		        AND DATEDIFF('$today',dob)<(365*15) 
		        AND active='1' group by gender";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				if ($result['gender'] == 1) {
					$male_below_fifteen_years = $result['total'];
				} else if ($result['gender'] == 2) {
					$female_below_fifteen_years = $result['total'];
				}
			}
		}

		//Get Patients enrolled in care above 15 years
		$sql = "SELECT COUNT(*) AS total,gender 
		        FROM patient 
		        WHERE MONTH(date_enrolled)='$month' 
		        AND YEAR(date_enrolled)='$year' 
		        AND DATEDIFF('$today',dob)>=(365*15) 
		        AND active='1'  
		        GROUP BY gender";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				if ($result['gender'] == 1) {
					$male_above_fifteen_years = $result['total'];
				} else if ($result['gender'] == 2) {
					$female_above_fifteen_years = $result['total'];
				}
			}
		}

		$data = array();
		$data['J13'] = $below_one_year;
		$data['J14'] = $male_below_fifteen_years;
		$data['L15'] = $female_below_fifteen_years;
		$data['J15'] = $male_above_fifteen_years;
		$data['L15'] = $female_above_fifteen_years;
		return $data;
	}

	public function currently_in_care($selected_period = "March-2014") {
		//Variables
		$period = explode('-', $selected_period);
		$year = $period[1];
		$month = date('m', strtotime($period[0]));
		$today = date('Y-m-t', strtotime("$selected_period"));

		$from = date('Y-m-01', strtotime($selected_period . "-2 month"));

		$below_one_year = 0;
		$male_below_fifteen_years = 0;
		$female_below_fifteen_years = 0;
		$male_above_fifteen_years = 0;
		$female_above_fifteen_years = 0;

		/*
		 * Currently in Care - Below 1 year (all art/oi and female adult pmtct)
		 * Currently in Care - Below 15 years (all art/oi and female adult pmtct)
		 * Currently in Care - 15 years & older (all art/oi and female adult pmtct)
		 */
		$sql = "SELECT DATEDIFF('$today',p.dob) AS age,g.name as gender,rst.name as service 
				FROM patient p
				LEFT JOIN gender g ON g.id=p.gender
				LEFT JOIN regimen_service_type rst ON rst.id=p.service
				LEFT JOIN patient_status ps ON ps.id=p.current_status
				WHERE p.date_enrolled <='$today'
				AND (rst.name LIKE '%art%' OR rst.name LIKE '%oi%' OR rst.name LIKE '%pmtct%')
				AND ps.Name LIKE '%active%'
				GROUP BY p.patient_number_ccc";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				if (strtolower($result['gender']) == "female") {
					if ($result['age'] < 365 && strtolower($result['service'] != "pmtct")) {
						$below_one_year++;
					} else if ($result['age'] > 365 && $result['age'] < (365 * 15) && strtolower($result['service'] != "pmtct")) {
						$female_below_fifteen_years++;
					} else if ($result['age'] >= (365 * 15)) {
						$female_above_fifteen_years++;
					}
				} else if (strtolower($result['gender']) == "male") {
					if ($result['age'] < 365 && strtolower($result['service'] != "pmtct")) {
						$below_one_year++;
					} else if ($result['age'] > 365 && $result['age'] < (365 * 15) && strtolower($result['service'] != "pmtct")) {
						$male_below_fifteen_years++;
					} else if ($result['age'] >= (365 * 15) && strtolower($result['service'] != "pmtct")) {
						$male_above_fifteen_years++;
					}
				}
			}
		}
		$data = array();
		$data['J19'] = $below_one_year;
		$data['J20'] = $male_below_fifteen_years;
		$data['L20'] = $female_below_fifteen_years;
		$data['J21'] = $male_above_fifteen_years;
		$data['L21'] = $female_above_fifteen_years;

		return $data;
	}

	public function all_art($selected_period = "") {
        //1.starting art

		//Variables
		$from = date('Y-m-01', strtotime($selected_period));
		$to = date('Y-m-t', strtotime($selected_period));
		$today = date('Y-m-t', strtotime($selected_period));
		$below_one_year = 0;
		$male_below_fifteen_years = 0;
		$female_below_fifteen_years = 0;
		$male_above_fifteen_years = 0;
		$female_above_fifteen_years = 0;
		$female_pregnant = 0;
		$yes_tb = 0;

		//Get patients starting on ART  below 1 year
		$sql = "SELECT COUNT(*) AS total 
		        FROM patient p 
		        LEFT JOIN regimen_service_type rst ON rst.id=p.service 
		        WHERE p.start_regimen_date 
		        BETWEEN '$from' 
		        AND '$to' 
		        AND DATEDIFF('$today',p.dob)<365 
		        AND rst.name LIKE '%art%'
		        AND p.active='1'";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			$below_one_year = $results[0]['total'];
		}

		//Get Patients starting on ART  below 15 years
		$sql = "SELECT COUNT(*) AS total,p.gender 
		        FROM patient p 
		        LEFT JOIN regimen_service_type rst ON rst.id=p.service 
		        WHERE p.start_regimen_date 
		        BETWEEN '$from' 
		        AND '$to' 
		        AND DATEDIFF('$today',p.dob)>=365 
		        AND DATEDIFF('$today',dob)<(365*15) 
		        AND rst.name LIKE '%art%' 
		        AND p.active='1'
		        GROUP BY p.gender";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				if ($result['gender'] == 1) {
					$male_below_fifteen_years = $result['total'];
				} else if ($result['gender'] == 2) {
					$female_below_fifteen_years = $result['total'];
				}
			}
		}

		//Get Patients starting on ART above 15 years
		$sql = "SELECT COUNT(*) AS total,p.gender 
		        FROM patient p 
		        LEFT JOIN regimen_service_type rst ON rst.id=p.service 
		        WHERE p.start_regimen_date 
		        BETWEEN '$from' 
		        AND '$to' 
		        AND DATEDIFF('$today',p.dob)>=(365*15) 
		        AND rst.name LIKE '%art%' 
		        AND p.active='1' 
		        GROUP BY p.gender";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				if ($result['gender'] == 1) {
					$male_above_fifteen_years = $result['total'];
				} else if ($result['gender'] == 2) {
					$female_above_fifteen_years = $result['total'];
				}
			}
		}

		//Get Patients starting on ART and are pregnant
		$sql = "SELECT COUNT(*) AS total,pregnant 
		        FROM patient 
		        WHERE start_regimen_date 
		        BETWEEN '$from' 
		        AND '$to' 
		        AND gender='2' 
		        AND active='1'
		        GROUP BY pregnant";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {

			foreach ($results as $result) {
				if ($result['pregnant'] == 1) {
					$female_pregnant = $result['total'];
				}
			}
		}

		//Get Patients starting on ART and have TB
		$sql = "SELECT COUNT(*) AS total,tb 
		        FROM patient 
		        WHERE start_regimen_date 
		        BETWEEN '$from' 
		        AND '$to' 
		        AND active='1'
		        GROUP BY tb";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {

			foreach ($results as $result) {
				if ($result['tb'] == 1) {
					$yes_tb = $result['total'];
				}
			}
		}

		$data = array();
		$data['J25'] = $below_one_year;
		$data['J26'] = $male_below_fifteen_years;
		$data['L26'] = $female_below_fifteen_years;
		$data['J27'] = $male_above_fifteen_years;
		$data['L27'] = $female_above_fifteen_years;
		$data['J29'] = $female_pregnant;
		$data['J30'] = $yes_tb;

        //2.revisits on art

		$from = date('Y-m-01', strtotime($selected_period . "-2 months"));
		$to = date('Y-m-t', strtotime($selected_period));

		$below_one_year = 0;
		$male_below_fifteen_years = 0;
		$female_below_fifteen_years = 0;
		$male_above_fifteen_years = 0;
		$female_above_fifteen_years = 0;

		$sql = "SELECT DATEDIFF('$to', dob ) AS age, gender_desc AS gender, regimen_service_type AS service
				FROM v_patient_visits
				WHERE dispensing_date
				BETWEEN  '$from'
				AND  '$to'
				GROUP BY patient_number_ccc";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				//check if service is art
				if(strtolower($result['service']) == "art"){
					if (strtolower($result['gender']) == "female") {
						if ($result['age'] < 365) {
							$below_one_year++;
						} else if ($result['age'] > 365 && $result['age'] < (365 * 15)) {
							$female_below_fifteen_years++;
						} else if ($result['age'] >= (365 * 15)) {
							$female_above_fifteen_years++;
						}
					} else if (strtolower($result['gender']) == "male") {
						if ($result['age'] < 365) {
							$below_one_year++;
						} else if ($result['age'] > 365 && $result['age'] < (365 * 15)) {
							$male_below_fifteen_years++;
						} else if ($result['age'] >= (365 * 15)) {
							$male_above_fifteen_years++;
						}
					}
			   }
			}
		}
		$data['J33'] = $below_one_year;
		$data['J34'] = $male_below_fifteen_years;
		$data['L34'] = $female_below_fifteen_years;
		$data['J35'] = $male_above_fifteen_years;
		$data['L35'] = $female_above_fifteen_years;

        //3.Currently on art
	    $data['J39'] = $data['J25'] + $data['J33'];
		$data['J40'] = $data['J26'] + $data['J34'];
		$data['L40'] = $data['L26'] + $data['L34'];
		$data['J41'] = $data['J27'] + $data['J35'];
		$data['L41'] = $data['L27'] + $data['L35'];

		return $data;
	}

	public function cumulative_ever_on_art($selected_period = "") {
		//Variables
		$from = date('Y-m-01', strtotime($selected_period));
		$to = date('Y-m-t', strtotime($selected_period));

		$below_one_year = 0;
		$male_below_fifteen_years = 0;
		$female_below_fifteen_years = 0;
		$male_above_fifteen_years = 0;
		$female_above_fifteen_years = 0;

		$sql = "SELECT DATEDIFF('$to',p.dob) as age,g.name as gender
				FROM patient p 
				LEFT JOIN gender g ON g.id=p.gender
				LEFT JOIN regimen_service_type rst ON rst.id=p.service
				WHERE p.date_enrolled <='$to'
				AND rst.name LIKE '%art%'
		        AND p.active='1'
		        GROUP BY p.id";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				if (strtolower($result['gender']) == "female" && $result['age'] >= (365 * 15)) {
					$female_above_fifteen_years++;
				} else if (strtolower($result['gender']) == "male" && $result['age'] >= (365 * 15)) {
					$male_above_fifteen_years++;
				} else if (strtolower($result['gender']) == "female" && $result['age'] < (365 * 15)) {
					$female_below_fifteen_years++;
				} else if (strtolower($result['gender']) == "male" && $result['age'] < (365 * 15)) {
					$male_below_fifteen_years++;
				}
			}
		}
		$data = array();
		$data['J45'] = $male_below_fifteen_years;
		$data['L45'] = $female_below_fifteen_years;
		$data['J46'] = $male_above_fifteen_years;
		$data['L46'] = $female_above_fifteen_years;
		return $data;
	}

	public function survival_and_retention($selected_period = "March-2014") {
		//Variables
		$from = date('Y-m-01', strtotime($selected_period . "-12 months"));
		$to = date('Y-m-t', strtotime($selected_period . "-12 months"));

		$art_net_cohort = 0;
		$original_first_line = 0;
		$alternate_first_line = 0;
		$second_line = 0;

		//art net cohort
		$sql = "SELECT COUNT(DISTINCT(p.id)) as total
		        FROM patient p 
		        INNER JOIN
		        (SELECT p.id
		        FROM patient p 
		        LEFT JOIN regimen_service_type rst ON rst.id=p.service
		        WHERE p.start_regimen_date
		        BETWEEN '$from'
		        AND '$to'
		        AND p.active='1'
		        AND rst.name LIKE '%art%') as past ON past.id=p.id
		        LEFT JOIN patient_status ps ON ps.id=p.current_status
		        WHERE ps.name NOT LIKE '%transfer out%'";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			$art_net_cohort = $results[0]['total'];
		}

		//original 1st line
		$sql = "SELECT COUNT(DISTINCT(p.id)) as total
		        FROM patient p 
		        INNER JOIN
		        (SELECT p.id,p.start_regimen
		        FROM patient p 
		        LEFT JOIN regimen_service_type rst ON rst.id=p.service
		        WHERE p.start_regimen_date
		        BETWEEN '$from'
		        AND '$to'
		        AND p.active='1'
				AND rst.name LIKE '%art%') as past ON past.id=p.id
		        LEFT JOIN patient_status ps ON ps.id=p.current_status
		        WHERE ps.Name NOT LIKE '%transfer out%'
		        AND past.start_regimen=p.current_regimen";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			$original_first_line = $results[0]['total'];
		}
		//alternate first line
		$sql = "SELECT COUNT(DISTINCT(p.id)) as total
		        FROM patient p 
		        INNER JOIN
		        (SELECT p.id,p.start_regimen
		        FROM patient p 
		        LEFT JOIN regimen_service_type rst ON rst.id=p.service
		        WHERE p.start_regimen_date
		        BETWEEN '$from'
		        AND '$to'
		        AND p.active='1'
				AND rst.name LIKE '%art%') as past ON past.id=p.id
		        LEFT JOIN patient_status ps ON ps.id=p.current_status
		        WHERE ps.Name NOT LIKE '%transfer out%'
		        AND past.start_regimen !=p.current_regimen
		        AND p.current_regimen IN(SELECT id FROM regimen WHERE line='1')";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			$alternate_first_line = $results[0]['total'];
		}

		//second line
		$sql = "SELECT COUNT(DISTINCT(p.id)) as total
		        FROM patient p 
		        INNER JOIN
		        (SELECT p.id,p.start_regimen
		        FROM patient p 
		        LEFT JOIN regimen_service_type rst ON rst.id=p.service
		        WHERE p.start_regimen_date
		        BETWEEN '$from'
		        AND '$to'
		        AND p.active='1'
				AND rst.name LIKE '%art%') as past ON past.id=p.id
		        LEFT JOIN patient_status ps ON ps.id=p.current_status
		        WHERE ps.Name NOT LIKE '%transfer out%'
		        AND past.start_regimen !=p.current_regimen
		        AND p.current_regimen IN(SELECT id FROM regimen WHERE line >='2')";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			$alternate_first_line = $results[0]['total'];
		}

		$data = array();
		$data['J50'] = $art_net_cohort;
		$data['J51'] = $original_first_line;
		$data['J52'] = $alternate_first_line;
		$data['J53'] = $second_line;

		return $data;
	}

	public function screening($selected_period = "") {
		//Variables
		$from = date('Y-m-01', strtotime($selected_period));
		$to = date('Y-m-t', strtotime($selected_period));

		$below_one_year = 0;
		$male_below_fifteen_years = 0;
		$female_below_fifteen_years = 0;
		$male_above_fifteen_years = 0;
		$female_above_fifteen_years = 0;

		$sql = "SELECT DATEDIFF('$to',p.dob) as age,g.name as gender
				FROM patient p 
				LEFT JOIN gender g ON g.id=p.gender
				WHERE p.date_enrolled <='$to'
				AND p.tb_test='1'
		        AND p.active='1'
		        GROUP BY p.id";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				if (strtolower($result['gender']) == "female" && $result['age'] >= (365 * 15)) {
					$female_above_fifteen_years++;
				} else if (strtolower($result['gender']) == "male" && $result['age'] >= (365 * 15)) {
					$male_above_fifteen_years++;
				} else if (strtolower($result['gender']) == "female" && $result['age'] < (365 * 15)) {
					$female_below_fifteen_years++;
				} else if (strtolower($result['gender']) == "male" && $result['age'] < (365 * 15)) {
					$male_below_fifteen_years++;
				}
			}
		}
		$data = array();
		$data['J57'] = $male_below_fifteen_years;
		$data['L57'] = $female_below_fifteen_years;
		$data['J58'] = $male_above_fifteen_years;
		$data['L58'] = $female_above_fifteen_years;
		return $data;
	}

	public function prevention_with_positives($selected_period = "") {
		//Variables
		$to = date('Y-m-t', strtotime($selected_period));
		$condoms = 0;
		$modern_contraceptives = 0;

		//Get patients using modern contraceptives
		$sql = "SELECT COUNT(DISTINCT(p.id)) as total 
                FROM patient p
                WHERE p.date_enrolled <='$to'
                AND p.fplan IN 
                (SELECT indicator 
                 FROM family_planning 
                 WHERE name NOT LIKE '%condom%'
                 )";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			$modern_contraceptives = $results[0]['total'];
		}

		//Get patients using condoms
		$sql = "SELECT COUNT(DISTINCT(p.id)) as total 
                FROM patient p
                WHERE p.date_enrolled <='$to'
                AND p.fplan IN 
                (SELECT indicator 
                 FROM family_planning 
                 WHERE name LIKE '%condom%'
                 )";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			$condoms = $results[0]['total'];
		}
		$data = array();
		$data['J63'] = $modern_contraceptives;
		$data['J64'] = $condoms;
		return $data;
	}

	public function hiv_care_visits($selected_period = "") {
		//Variables
		$from = date('Y-m-01', strtotime($selected_period));
		$to = date('Y-m-t', strtotime($selected_period));

		$female_18 = 0;
		$scheduled_visits = 0;
		$unscheduled_visits = 0;

		$sql = "SELECT patient_number_ccc,gender_desc as gender,dispensing_date,appointment, regimen_service_type AS service
		        FROM v_patient_visits
		        WHERE dispensing_date
		        BETWEEN '$from'
				AND '$to'
				GROUP BY patient_number_ccc";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				if (strtolower($result['service']) == "art") {
					if (strtolower($result['gender']) == "female") {
						$female_18++;
					}
					if ($result['appointment'] != $result['dispensing_date']) {
						$unscheduled_visits++;
					} else if ($result['appointment'] == $result['dispensing_date']) {
						$scheduled_visits++;
					}
			    }
			}
		}
		$data = array();
		$data['J67'] = $female_18;
		$data['J68'] = $scheduled_visits;
		$data['J69'] = $unscheduled_visits;

		return $data;
	}

	public function type_of_exposure($period = "") {
		//Variables
		$from = date('Y-m-01', strtotime($period));
		$to = date('Y-m-t', strtotime($period));

		$occassional_male = 0;
		$sexual_assualt_male = 0;
		$other_reason_male = 0;
		$occassional_female = 0;
		$sexual_assualt_female = 0;
		$other_reason_female = 0;

		$sql = "SELECT pr.name,g.name as gender
                FROM patient p
                LEFT JOIN gender g ON g.id=p.gender
                LEFT JOIN pep_reason pr ON pr.id=p.pep_reason
                WHERE p.date_enrolled 
                BETWEEN '$from'
                AND '$to'
                GROUP BY p.id";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				if ($result['name'] == "Occupational" && strtolower($result['gender']) == "female") {
					$occassional_female++;
				} else if ($result['name'] == "Sexual assault" && strtolower($result['gender']) == "female") {
					$sexual_assualt_female++;
				} else if (strtolower($result['gender']) == "female") {
					$other_reason_female++;
				} else if ($result['name'] == "Occupational" && strtolower($result['gender']) == "male") {
					$occassional_male++;
				} else if ($result['name'] == "Sexual assault" && strtolower($result['gender']) == "male") {
					$sexual_assualt_male++;
				} else if (strtolower($result['gender']) == "male") {
					$other_reason_male++;
				}
			}
		}

		$data = array();
		$data['D92'] = $occassional_male;
		$data['D93'] = $sexual_assualt_male;
		$data['D94'] = $other_reason_male;
		$data['H92'] = $occassional_female;
		$data['H93'] = $sexual_assualt_female;
		$data['H94'] = $other_reason_female;

		return $data;
	}

	public function provided_with_prophylaxis($period = "") {
		//Variables
		$from = date('Y-m-01', strtotime($period));
		$to = date('Y-m-t', strtotime($period));

		$occassional_male = 0;
		$sexual_assualt_male = 0;
		$other_reason_male = 0;
		$occassional_female = 0;
		$sexual_assualt_female = 0;
		$other_reason_female = 0;

		$sql = "SELECT pr.name,g.name as gender
                FROM patient p
                LEFT JOIN gender g ON g.id=p.gender
                LEFT JOIN pep_reason pr ON pr.id=p.pep_reason
                LEFT JOIN regimen_service_type rst ON rst.id=p.service
                WHERE p.date_enrolled 
                BETWEEN '$from'
                AND '$to'
                AND rst.name LIKE '%pep%'
                GROUP BY p.id";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				if ($result['name'] == "Occupational" && strtolower($result['gender']) == "female") {
					$occassional_female++;
				} else if ($result['name'] == "Sexual assault" && strtolower($result['gender']) == "female") {
					$sexual_assualt_female++;
				} else if (strtolower($result['gender']) == "female") {
					$other_reason_female++;
				} else if ($result['name'] == "Occupational" && strtolower($result['gender']) == "male") {
					$occassional_male++;
				} else if ($result['name'] == "Sexual assault" && strtolower($result['gender']) == "male") {
					$sexual_assualt_male++;
				} else if (strtolower($result['gender']) == "male") {
					$other_reason_male++;
				}
			}
		}

		$data = array();
		$data['D97'] = $occassional_male;
		$data['D98'] = $sexual_assualt_male;
		$data['D99'] = $other_reason_male;
		$data['H97'] = $occassional_female;
		$data['H98'] = $sexual_assualt_female;
		$data['H99'] = $other_reason_female;

		return $data;
	}

	public function getMoreHelp($stock_type = '2', $start_date = '', $end_date = '') {
		//Check if user is logged in
		if ($this -> session -> userdata("user_id")) {

			/* Server side start */
			$data = array();
			$aColumns = array('drug');
			$iDisplayStart = $this -> input -> get_post('iDisplayStart', true);
			$iDisplayLength = $this -> input -> get_post('iDisplayLength', true);
			$iSortCol_0 = $this -> input -> get_post('iSortCol_0', false);
			$iSortingCols = $this -> input -> get_post('iSortingCols', true);
			$sSearch = $this -> input -> get_post('sSearch', true);
			$sEcho = $this -> input -> get_post('sEcho', true);

			// Paging
			if (isset($iDisplayStart) && $iDisplayLength != '-1') {
				$this -> db -> limit($this -> db -> escape_str($iDisplayLength), $this -> db -> escape_str($iDisplayStart));
			}

			// Ordering
			if (isset($iSortCol_0)) {
				for ($i = 0; $i < intval($iSortingCols); $i++) {
					$iSortCol = $this -> input -> get_post('iSortCol_' . $i, true);
					$bSortable = $this -> input -> get_post('bSortable_' . intval($iSortCol), true);
					$sSortDir = $this -> input -> get_post('sSortDir_' . $i, true);

					if ($bSortable == 'true') {
						$this -> db -> order_by($aColumns[intval($this -> db -> escape_str($iSortCol))], $this -> db -> escape_str($sSortDir));
					}
				}
			}
			/*
			 * Filtering
			 * NOTE this does not match the built-in DataTables filtering which does it
			 * word by word on any field. It's possible to do here, but concerned about efficiency
			 * on very large tables, and MySQL's regex functionality is very limited
			 */
			if (isset($sSearch) && !empty($sSearch)) {
				for ($i = 0; $i < count($aColumns); $i++) {
					$bSearchable = $this -> input -> get_post('bSearchable_' . $i, true);
					// Individual column filtering
					if (isset($bSearchable) && $bSearchable == 'true') {
						$this -> db -> or_like($aColumns[$i], $this -> db -> escape_like_str($sSearch));
					}
				}
			}

			/*
			 * Outer Loop through all active drugs
			 */
			$first_value = "AND ccc_store_sp = $stock_type";
			$second_value = "AND dst.ccc_store_sp = $stock_type";
			// Select Data
			$this -> db -> select('SQL_CALC_FOUND_ROWS ' . str_replace(' , ', ' ', implode(', ', $aColumns)), false);
			$this -> db -> select("dc.id,dc.pack_size,u.name");
			$today = date('Y-m-d');
			$this -> db -> from("drugcode dc");
			$this -> db -> join("drug_unit u", "u.id=dc.unit", 'left outer');
			$this -> db -> where("dc.Enabled", 1);
			$rResult = $this -> db -> get();

			// Data set length after filtering
			$this -> db -> select('FOUND_ROWS() AS found_rows');
			$iFilteredTotal = $this -> db -> get() -> row() -> found_rows;

			// Total data set length
			$this -> db -> select("dc.*");
			$this -> db -> from("drugcode dc");
			$this -> db -> join("drug_unit u", "u.id=dc.unit", 'left outer');
			$this -> db -> where("dc.Enabled", 1);
			$tot_drugs = $this -> db -> get();
			$iTotal = count($tot_drugs -> result_array());

			$prev_start = date("Y-m-d", strtotime("-1 month", strtotime($start_date)));
			$prev_end = date("Y-m-d", strtotime("-1 month", strtotime($end_date)));

			// Output
			$output = array('sEcho' => intval($sEcho), 'iTotalRecords' => $iTotal, 'iTotalDisplayRecords' => $iFilteredTotal, 'aaData' => array());
			foreach ($rResult->result_array() as $aRow) {
				$row = array();
				$drug_id = $aRow['id'];
				$row[] = $aRow['drug'];

				//Start of Beginning Balance
				$sql = "SELECT SUM( dst.balance ) AS total FROM drug_stock_movement dst, (SELECT drug, batch_number, MAX( transaction_date ) AS trans_date FROM  `drug_stock_movement` WHERE transaction_date BETWEEN  '$prev_start' AND  '$prev_end' AND drug ='$drug_id' $first_value GROUP BY batch_number) AS temp WHERE dst.drug = temp.drug AND dst.batch_number = temp.batch_number AND dst.transaction_date = temp.trans_date $second_value";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				if ($results) {
					if ($results[0]['total'] != null) {
						$row[] = $results[0]['total'];
					} else {
						$row[] = 0;
					}
				} else {
					$row[] = 0;
				}

				//End of Beginning Balance
				//Start of Other Transactions
				$start_date = date('Y-m-d', strtotime($start_date));
				$end_date = date('Y-m-d', strtotime($end_date));
				$sql = "SELECT trans.name, trans.id, trans.effect, dsm.in_total, dsm.out_total FROM 
						(SELECT id, name, effect FROM transaction_type 
						WHERE name LIKE  '%received%' OR name LIKE  '%adjustment%' 
						OR name LIKE  '%return%' OR name LIKE  '%dispense%' OR name LIKE  '%issue%' 
						OR name LIKE  '%loss%' OR name LIKE  '%ajustment%' 
						OR name LIKE  '%physical%count%' OR name LIKE  '%starting%stock%') AS trans 
						LEFT JOIN (SELECT transaction_type, SUM( quantity ) AS in_total, SUM( quantity_out ) AS out_total 
						FROM drug_stock_movement WHERE transaction_date 
						BETWEEN  '$start_date' AND  '$end_date' AND drug =  '$drug_id' $first_value 
						GROUP BY transaction_type) AS dsm ON trans.id = dsm.transaction_type GROUP BY trans.name";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				if ($results) {
					foreach ($results as $result) {
						$effect = $result['effect'];
						$trans_name = $result['name'];
						if ($effect == 1) {
							if ($result['in_total'] != null) {
								$total = $result['in_total'];
							} else {
								$total = 0;
							}
						} else {
							if ($result['out_total'] != null) {
								$total = $result['out_total'];
							} else {
								$total = 0;
							}
						}
						$row[] = $total;
					}
				}
				//End of Other Transactions
				$output['aaData'][] = $row;
			}
			echo json_encode($output);

		}//Check if user is logged in end

	}

	public function getHelp($stock_type = '2', $start_date = '2013-08-01', $end_date = '2013-08-31') {
		/*
		 * Loop through all respective transaction types and add beginning balance at the beginning
		 * Outer Loop through all active drugs
		 * Inner Loop through all respective transaction types for the outer drug
		 */

		$first_value = "AND ccc_store_sp = $stock_type";
		$second_value = "AND dst.ccc_store_sp = $stock_type";
		
		$drugs = Drugcode::getEnabledDrugs();
		$transactions = Transaction_Type::getAllTypes();
		$overall_array = array();
		$trans_sections = array();
		$prev_start = date("Y-m-d", strtotime("-1 month", strtotime($start_date)));
		$prev_end = date("Y-m-d", strtotime("-1 month", strtotime($end_date)));
		$trans_sections['Beginning Balance'] = 0;
		foreach ($transactions as $transaction) {
			$trans_sections[$transaction['Name']] = $transaction['id'];
		}
		foreach ($drugs as $drug) {
			$drug_id = $drug['id'];
			$drug_name = $drug['Drug'];
			foreach ($trans_sections as $section_index => $sections) {
				if ($sections == 0) {
					/*
					 * Runs when transaction is beginngin balance
					 * Get Beginning Balance of drug
					 */
					$sql = "SELECT SUM( dst.balance ) AS total FROM drug_stock_movement dst, 
							(SELECT drug, batch_number, MAX( transaction_date ) AS trans_date FROM  `drug_stock_movement` 
							WHERE transaction_date BETWEEN  '$prev_start' AND  '$prev_end' AND drug ='$drug_id' $first_value 
							GROUP BY batch_number) AS temp WHERE dst.drug = temp.drug AND dst.batch_number = temp.batch_number 
							AND dst.transaction_date = temp.trans_date $second_value";
				} else {
					$effect = Transaction_Type::getEffect($sections);
					if ($effect['Effect'] == 1) {
						$balance_value = "quantity";
					} else {
						$balance_value = "quantity_out";
					}
					$sql = "SELECT SUM($balance_value) AS total FROM  `drug_stock_movement` 
							WHERE transaction_date BETWEEN  '$start_date' AND  '$end_date' $first_value 
							AND transaction_type ='$sections' AND drug='$drug_id'";

				}
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				if ($results) {
					if ($results[0]['total'] != null) {
						$overall_array[$drug_name][$section_index] = $results[0]['total'];
					} else {
						$overall_array[$drug_name][$section_index] = 0;
					}
				} else {
					$overall_array[$drug_name][$section_index] = 0;
				}
			}
		}

		

	}

	public function listing($data = "") {
		$data['content_view'] = "report_v";
		$this -> base_params($data);
	}

	public function patient_enrolled($from = "", $to = "", $supported_by = 0) {
		//Variables
		$facility_code = $this -> session -> userdata("facility");
		$from = date('Y-m-d', strtotime($from));
		$to = date('Y-m-d', strtotime($to));

		$source_total_percentage = 0;
		$source_totals = array();
		$overall_adult_male = 0;
		$overall_adult_female = 0;
		$overall_child_male = 0;
		$overall_child_female = 0;

		$total = 0;
		$overall_adult_male_art = 0;
		$overall_adult_male_pep = 0;
		$overall_adult_male_oi = 0;

		$overall_adult_female_art = 0;
		$overall_adult_female_pep = 0;
		$overall_adult_female_pmtct = 0;
		$overall_adult_female_oi = 0;

		$overall_child_male_art = 0;
		$overall_child_male_pep = 0;
		$overall_child_male_pmtct = 0;
		$overall_child_male_oi = 0;

		$overall_child_female_art = 0;
		$overall_child_female_pep = 0;
		$overall_child_female_pmtct = 0;
		$overall_child_female_oi = 0;

		if ($supported_by == 0) {
			$supported_query = " ";
		}
		if ($supported_by == 1) {
			$supported_query = "AND supported_by=1 ";
		}
		if ($supported_by == 2) {
			$supported_query = "AND supported_by=2 ";
		}

		$dyn_table = "<table border='1' id='patient_listing'  cellpadding='5' class='dataTables'>";
		$dyn_table .= "<thead>
			<tr>
				<th ></th>
				<th >Total</th><th></th>
				<th > Adult</th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th>
				<th > Children </th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th>
			</tr>
			<tr>
				<th></th>
				<th ></th>
				<th ></th>
				<th >Male</th><th></th><th></th><th></th><th></th><th></th>
				<th >Female</th><th></th><th></th><th></th><th></th><th></th><th></th><th></th>
				<th >Male</th><th></th><th></th><th></th><th></th><th></th><th></th><th></th>
				<th >Female</th><th></th><th></th><th></th><th></th><th></th><th></th><th></th>
			</tr>
			<tr>
				<th></th>
				<th></th>
				<th></th>
				<th >ART</th><th></th>
				<th >PEP</th><th></th>
				<th >OI</th><th></th>
				<th >ART</th><th></th>
				<th >PEP</th><th></th>
				<th >PMTCT</th><th></th>
				<th >OI</th><th></th>
				<th >ART</th><th></th>
				<th >PEP</th><th></th>
				<th >PMTCT</th><th></th>
				<th >OI</th><th></th>
				<th >ART</th><th></th>
				<th >PEP</th><th></th>
				<th >PMTCT</th><th></th>
				<th >OI</th><th></th>
			</tr>
			<tr>
				<th>Source</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
			</tr>
		</thead><tbody>";

		//Get Total of all patients
		$sql = "SELECT count( * ) AS total FROM patient p LEFT JOIN patient_source ps ON ps.id = p.source WHERE date_enrolled BETWEEN '$from' AND '$to' $supported_query AND facility_code = '$facility_code' AND source !='' AND p.active='1'";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		$total = $results[0]['total'];

		//Get Totals for each Source
		$sql = "SELECT count(*) AS total,p.source,ps.name 
					FROM patient p LEFT JOIN patient_source ps ON ps.id = p.source 
					WHERE date_enrolled BETWEEN '$from' AND '$to' $supported_query AND facility_code = '$facility_code' AND source !=''  AND p.active='1' GROUP BY p.source";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				$source_totals[$result['source']] = $result['total'];
				$source = $result['source'];
				$source_name = strtoupper($result['name']);
				$source_code = $result['source'];
				$source_total = $result['total'];
				$source_total_percentage = number_format(($source_total / $total) * 100, 1);
				$dyn_table .= "<tr><td><b>$source_name</b></td><td>$source_total</td><td>$source_total_percentage</td>";
				//SQL for Adult Male Source
				$sql = "SELECT count(*) AS total_adult_male,p.source,ps.name,p.service,rst.name as service_name FROM patient p LEFT JOIN patient_source ps ON ps.id= p.source LEFT JOIN regimen_service_type rst ON rst.id = p.service  WHERE date_enrolled BETWEEN '$from' AND '$to' $supported_query AND facility_code = '$facility_code' AND source !='' AND p.gender=1 AND FLOOR(datediff('$from',p.dob)/365)>15 AND  p.source='$source_code' GROUP BY p.source,p.service";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				$total_adult_male_art = "-";
				$total_adult_male_pep = "-";
				$total_adult_male_oi = "-";
				$total_adult_male_art_percentage = "-";
				$total_adult_male_pep_percentage = "-";
				$total_adult_male_oi_percentage = "-";
				if ($results) {
					foreach ($results as $result) {
						$total_adult_male = $result['total_adult_male'];
						$overall_adult_male += $total_adult_male;
						$service_name = $result['service_name'];
						if ($service_name == "ART") {
							$overall_adult_male_art += $total_adult_male;
							$total_adult_male_art = number_format($total_adult_male);
							$total_adult_male_art_percentage = number_format(($total_adult_male / $source_total) * 100, 1);
						} else if ($service_name == "PEP") {
							$overall_adult_male_pep += $total_adult_male;
							$total_adult_male_pep = number_format($total_adult_male);
							$total_adult_male_pep_percentage = number_format(($total_adult_male_pep / $source_total) * 100, 1);
						} else if ($service_name == "OI Only") {
							$overall_adult_male_oi += $total_adult_male;
							$total_adult_male_oi = number_format($total_adult_male);
							$total_adult_male_oi_percentage = number_format(($total_adult_male_oi / $source_total) * 100, 1);
						}

					}
					$dyn_table .= "<td>$total_adult_male_art</td><td>$total_adult_male_art_percentage</td><td>$total_adult_male_pep</td><td>$total_adult_male_pep_percentage</td><td>$total_adult_male_oi</td><td>$total_adult_male_oi_percentage</td>";

				} else {
					$dyn_table .= "<td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td>";

				}
				//SQL for Adult Female Source
				$sql = "SELECT count(*) AS total_adult_female,p.source,ps.name,p.service,rst.name as service_name 
						FROM patient p LEFT JOIN patient_source ps ON ps.id = p.source LEFT JOIN regimen_service_type rst ON rst.id = p.service 
						WHERE date_enrolled BETWEEN '$from' AND '$to' $supported_query AND facility_code = '$facility_code' AND source !='' AND p.gender=2 AND FLOOR(datediff('$from',p.dob)/365)>15 AND  p.source='$source_code' AND p.active=1 GROUP BY p.source,p.service";
				//die();
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				$total_adult_female_art = "-";
				$total_adult_female_pep = "-";
				$total_adult_female_pmtct = "-";
				$total_adult_female_oi = "-";
				$total_adult_female_art_percentage = "-";
				$total_adult_female_pep_percentage = "-";
				$total_adult_female_pmtct_percentage = "-";
				$total_adult_female_oi_percentage = "-";

				if ($results) {
					foreach ($results as $result) {
						$total_adult_female = $result['total_adult_female'];
						$overall_adult_female += $total_adult_female;
						$service_name = $result['service_name'];
						if ($service_name == "ART") {
							$overall_adult_female_art += $total_adult_female;
							$total_adult_female_art = number_format($total_adult_female);
							$total_adult_female_art_percentage = number_format(($total_adult_female / $source_total) * 100, 1);
						} else if ($service_name == "PEP") {
							$overall_adult_female_pep += $total_adult_female;
							$total_adult_female_pep = number_format($total_adult_female);
							$total_adult_female_pep_percentage = number_format(($total_adult_female_pep / $source_total) * 100, 1);
						} else if ($service_name == "PMTCT") {
							$overall_adult_female_pmtct += $total_adult_female;
							$total_adult_female_pmtct = number_format($total_adult_female);
							$total_adult_female_pmtct_percentage = number_format(($total_adult_female_pmtct / $source_total) * 100, 1);
						} else if ($service_name == "OI Only") {
							$overall_adult_female_oi += $total_adult_female;
							$total_adult_female_oi = number_format($total_adult_female);
							$total_adult_female_oi_percentage = number_format(($total_adult_female_oi / $source_total) * 100, 1);
						}
					}
					$dyn_table .= "<td>$total_adult_female_art</td><td>$total_adult_female_art_percentage</td><td>$total_adult_female_pep</td><td>$total_adult_female_pep_percentage</td><td>$total_adult_female_pmtct</td><td>$total_adult_female_pmtct_percentage</td><td>$total_adult_female_oi</td><td>$total_adult_female_oi_percentage</td>";
				} else {
					$dyn_table .= "<td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td>";
				}
				//SQL for Child Male Source
				$sql = "SELECT count(*) AS total_child_male,p.source,ps.name,p.service,rst.name as service_name FROM patient p LEFT JOIN patient_source ps ON ps.id = p.source LEFT JOIN regimen_service_type rst ON rst.id = p.service WHERE date_enrolled BETWEEN '$from' AND '$to' $supported_query AND facility_code = '$facility_code' AND source !='' AND p.gender=1 AND FLOOR(datediff('$from',p.dob)/365)<=15 AND  p.source='$source_code' GROUP BY p.source,p.service";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				$total_child_male_art = "-";
				$total_child_male_pep = "-";
				$total_child_male_pmtct = "-";
				$total_child_male_oi = "-";
				$total_child_male_art_percentage = "-";
				$total_child_male_pep_percentage = "-";
				$total_child_male_pmtct_percentage = "-";
				$total_child_male_oi_percentage = "-";
				if ($results) {
					foreach ($results as $result) {
						$total_child_male = $result['total_child_male'];
						$overall_child_male += $total_child_male;
						$service_name = $result['service_name'];
						if ($service_name == "ART") {
							$overall_child_male_art += $total_child_male;
							$total_child_male_art = number_format($total_child_male);
							$total_child_male_art_percentage = number_format(($total_child_male / $source_total) * 100, 1);
						} else if ($service_name == "PEP") {
							$overall_child_male_pep += $total_child_male;
							$total_child_male_pep = number_format($total_child_male);
							$total_child_male_pep_percentage = number_format(($total_child_male_pep / $source_total) * 100, 1);
						} else if ($service_name == "PMTCT") {
							$overall_child_male_pmtct += $total_child_male;
							$total_child_male_pmtct = number_format($total_child_male);
							$total_child_male_pmtct_percentage = number_format(($total_child_male_pmtct / $source_total) * 100, 1);
						} else if ($service_name == "OI Only") {
							$overall_child_male_oi += $total_child_male;
							$total_child_male_oi = number_format($total_child_male);
							$total_child_male_oi_percentage = number_format(($total_child_male_oi / $source_total) * 100, 1);
						}

					}
					$dyn_table .= "<td>$total_child_male_art</td><td>$total_child_male_art_percentage</td><td>$total_child_male_pep</td><td>$total_child_male_pep_percentage</td><td>$total_child_male_pmtct</td><td>$total_child_male_pmtct_percentage</td><td>$total_child_male_oi</td><td>$total_child_male_oi_percentage</td>";
				} else {
					$dyn_table .= "<td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td>";
				}
				//SQL for Child Female Source
				$sql = "SELECT count(*) AS total_child_female,p.source,ps.name,p.service,rst.name as service_name FROM patient p LEFT JOIN patient_source ps ON ps.id = p.source LEFT JOIN regimen_service_type rst ON rst.id = p.service WHERE date_enrolled BETWEEN '$from' AND '$to' $supported_query AND facility_code = '$facility_code' AND source !='' AND p.gender=2 AND FLOOR(datediff('$from',p.dob)/365) < 15 AND  p.source='$source_code' GROUP BY p.source,p.service";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				$total_child_female_art = "-";
				$total_child_female_pep = "-";
				$total_child_female_pmtct = "-";
				$total_child_female_oi = "-";
				$total_child_female_art_percentage = "-";
				$total_child_female_pep_percentage = "-";
				$total_child_female_pmtct_percentage = "-";
				$total_child_female_oi_percentage = "-";
				$overall_child_female = 0;
				$service_name = "";
				$overall_child_male = 0;
				if ($results) {
					foreach ($results as $result) {
						$total_child_female = $result['total_child_female'];
						$overall_child_female += $total_child_female;
						$service_name = $result['service_name'];
						if ($service_name == "ART") {
							$overall_child_female_art += $total_child_female;
							$total_child_female_art = number_format($total_child_female);
							$total_child_female_art_percentage = number_format(($total_child_female / $source_total) * 100, 1);
						} else if ($service_name == "PEP") {
							$overall_child_female_pep += $total_child_female;
							$total_child_female_pep = number_format($total_child_female);
							$total_child_female_pep_percentage = number_format(($total_child_female_pep / $source_total) * 100, 1);
						} else if ($service_name == "PMTCT") {
							$overall_child_female_pmtct += $total_child_female;
							$total_child_female_pmtct = number_format($total_child_female);
							$total_child_female_pmtct_percentage = number_format(($total_child_female_pmtct / $source_total) * 100, 1);
						} else if ($service_name == "OI Only") {
							$overall_child_female_oi += $total_child_female;
							$total_child_female_oi = number_format($total_child_female);
							$total_child_female_oi_percentage = number_format(($total_child_female_oi / $source_total) * 100, 1);
						}

					}
					$dyn_table .= "<td>$total_child_female_art</td><td>$total_child_female_art_percentage</td><td>$total_child_female_pep</td><td>$total_child_female_pep_percentage</td><td>$total_child_female_pmtct</td><td>$total_child_female_pmtct_percentage</td><td>$total_child_female_oi</td><td>$total_child_female_oi_percentage</td>";
				} else {
					$dyn_table .= "<td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td>";
				}
			}
			$overall_art_male_percent = number_format(($overall_adult_male_art / $total) * 100, 1);
			$overall_pep_male_percent = number_format(($overall_adult_male_pep / $total) * 100, 1);
			$overall_oi_male_percent = number_format(($overall_adult_male_oi / $total) * 100, 1);

			$overall_art_female_percent = number_format(($overall_adult_female_art / $total) * 100, 1);
			$overall_pep_female_percent = number_format(($overall_adult_female_pep / $total) * 100, 1);
			$overall_pmtct_female_percent = number_format(($overall_adult_female_pmtct / $total) * 100, 1);
			$overall_oi_female_percent = number_format(($overall_adult_female_oi / $total) * 100, 1);

			$overall_art_childmale_percent = number_format(($overall_child_male_art / $total) * 100, 1);
			$overall_pep_childmale_percent = number_format(($overall_child_male_pep / $total) * 100, 1);
			$overall_pmtct_childmale_percent = number_format(($overall_child_male_pmtct / $total) * 100, 1);
			$overall_oi_childmale_percent = number_format(($overall_child_male_oi / $total) * 100, 1);

			$overall_art_childfemale_percent = number_format(($overall_child_female_art / $total) * 100, 1);
			$overall_pep_childfemale_percent = number_format(($overall_child_female_pep / $total) * 100, 1);
			$overall_pmtct_childfemale_percent = number_format(($overall_child_female_pmtct / $total) * 100, 1);
			$overall_oi_childfemale_percent = number_format(($overall_child_female_oi / $total) * 100, 1);
			$dyn_table .= "</tbody><tfoot><tr><td>TOTALS</td><td>$total</td><td>100</td><td>$overall_adult_male_art</td><td>$overall_art_male_percent</td><td>$overall_adult_male_pep</td><td>$overall_pep_male_percent</td><td>$overall_adult_male_oi</td><td>$overall_oi_male_percent</td><td>$overall_adult_female_art</td><td>$overall_art_female_percent</td><td>$overall_adult_female_pep</td><td>$overall_pep_female_percent</td><td>$overall_adult_female_pmtct</td><td>$overall_pmtct_female_percent</td><td>$overall_adult_female_oi</td><td>$overall_oi_female_percent</td><td>$overall_child_male_art</td><td>$overall_art_childmale_percent</td><td>$overall_child_male_pep</td><td>$overall_pep_childmale_percent</td><td>$overall_child_male_pmtct</td><td>$overall_pmtct_childmale_percent</td><td>$overall_child_male_oi</td><td>$overall_oi_childmale_percent</td><td>$overall_child_female_art</td><td>$overall_art_childfemale_percent</td><td>$overall_child_female_pep</td><td>$overall_pep_childfemale_percent</td><td>$overall_child_female_pmtct</td><td>$overall_pmtct_childfemale_percent</td><td>$overall_child_female_oi</td><td>$overall_oi_childfemale_percent</td></tr></tfoot></table>";

		} else {
			$dyn_table .= "<tbody></tbody><tfoot>";
		}
		$dyn_table .= "</tfoot></table>";

		$data['dyn_table'] = $dyn_table;
		$data['from'] = date('d-M-Y', strtotime($from));
		$data['to'] = date('d-M-Y', strtotime($to));
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "standard_report_row";
		$data['selected_report_type'] = "Standard Reports";
		$data['report_title'] = "Number of Patients Enrolled in Period";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/no_of_patients_enrolled_v';
		$this -> load -> view('template', $data);

	}

	public function getScheduledPatients($from = "", $to = "") {
		//Variables
		$visited = 0;
		$not_visited = 0;
		$visited_later = 0;
		$row_string = "";
		$status = "";
		$overall_total = 0;
		$today = date('Y-m-d');
		$late_by = "";
		$facility_code = $this -> session -> userdata("facility");
		$from = date('Y-m-d', strtotime($from));
		$to = date('Y-m-d', strtotime($to));

		//Get all patients who have apppointments on the selected date range
		$sql = "select patient,appointment from patient_appointment where appointment between '$from' and '$to' and facility='$facility_code' group by patient,appointment";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		$row_string = "
			<table border='1' class='dataTables'>
				<thead >
					<tr>
						<th> Patient No </th>
						<th> Patient Name </th>
						<th> Phone No /Alternate No</th>
						<th> Phys. Address </th>
						<th> Sex </th>
						<th> Age </th>
						<th> Last Regimen </th>
						<th> Appointment Date </th>
						<th> Visit Status</th>
					</tr>
				</thead>
				<tbody>";
		if ($results) {
			foreach ($results as $result) {
				$patient = $result['patient'];
				$appointment = $result['appointment'];
				//Check if Patient visited on set appointment
				$sql = "select * from patient_visit where patient_id='$patient' and dispensing_date='$appointment' and facility='$facility_code'";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				if ($results) {
					//Visited
					$visited++;
					$status = "<span style='color:green;'>Yes</span>";
				} else if (!$results) {
					//Check if visited later or not
					$sql = "select DATEDIFF(dispensing_date,'$appointment')as late_by from patient_visit where patient_id='$patient' and dispensing_date>'$appointment' and facility='$facility_code' ORDER BY dispensing_date asc LIMIT 1";
					$query = $this -> db -> query($sql);
					$results = $query -> result_array();
					if ($results) {
						//Visited Later
						$visited_later++;
						$late_by = $results[0]['late_by'];
						$status = "<span style='color:blue;'>Late by $late_by Day(s)</span>";
					} else {
						//Not Visited
						$not_visited++;
						$status = "<span style='color:red;'>Not Visited</span>";
					}
				}
				$sql = "select patient_number_ccc as art_no,UPPER(first_name)as first_name,UPPER(other_name)as other_name,UPPER(last_name)as last_name, IF(gender=1,'Male','Female')as gender,UPPER(physical) as physical,phone,alternate,FLOOR(DATEDIFF('$today',dob)/365) as age,r.regimen_desc as last_regimen from patient,regimen r where patient_number_ccc='$patient' and current_regimen=r.id and facility_code='$facility_code'";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				if ($results) {
					foreach ($results as $result) {
						$patient_id = $result['art_no'];
						$first_name = $result['first_name'];
						$other_name = $result['other_name'];
						$last_name = $result['last_name'];
						$phone = $result['phone'];
						if (!$phone) {
							$phone = $result['alternate'];
						}
						$address = $result['physical'];
						$gender = $result['gender'];
						$age = $result['age'];
						$last_regimen = $result['last_regimen'];
						$appointment = date('d-M-Y', strtotime($appointment));
					}
					$row_string .= "<tr><td>$patient_id</td><td width='300' style='text-align:left;'>$first_name $other_name $last_name</td><td>$phone</td><td>$address</td><td>$gender</td><td>$age</td><td style='white-space:nowrap;'>$last_regimen</td><td>$appointment</td><td width='200px'>$status</td></tr>";
					$overall_total++;
				}
			}

		} else {
			//$row_string .= "<tr><td colspan='8'>No Data Available</td></tr>";
		}
		$row_string .= "</tbody></table>";
		$data['from'] = date('d-M-Y', strtotime($from));
		$data['to'] = date('d-M-Y', strtotime($to));
		$data['dyn_table'] = $row_string;
		$data['visited_later'] = $visited_later;
		$data['not_visited'] = $not_visited;
		$data['visited'] = $visited;
		$data['all_count'] = $overall_total;
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "visiting_patient_report_row";
		$data['selected_report_type'] = "Visiting Patients";
		$data['report_title'] = "List of Patients Scheduled to Visit";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/patients_scheduled_v';
		$this -> load -> view('template', $data);
	}

	public function getPatientMissingAppointments($from = "", $to = "") {
		//Variables
		$today = date('Y-m-d');
		$row_string = "";
		$overall_total = 0;
		$facility_code = $this -> session -> userdata("facility");
		$from = date('Y-m-d', strtotime($from));
		$to = date('Y-m-d', strtotime($to));
        
        //sql to get all appoitnments in period
		$sql = "SELECT pa.patient,
		               pa.appointment 
		        FROM patient_appointment pa
		        WHERE pa.appointment 
		        BETWEEN '$from' 
		        AND '$to'
		        AND facility='$facility_code' 
		        GROUP BY patient,appointment";
		$query = $this -> db -> query($sql);

		$results = $query -> result_array();
		$row_string .= "<table border='1' class='dataTables'>
			<thead>
				<tr>
					<th> ART ID </th>
					<th> Patient Name</th>
                                        <th> Type of Service</th>
					<th> Sex </th>
                                        <th> Age </th>
					<th> Contacts/Address </th>
					<th> Appointment Date </th>
					<th> Late by (days)</th>
				</tr>
			</thead>";
		if ($results) {
			foreach ($results as $result) {
				$patient = $result['patient'];
				$appointment = $result['appointment'];
				//Check if Patient visited on set appointment
				$sql = "SELECT * 
				        FROM patient_visit 
				        WHERE patient_id='$patient' 
				        AND dispensing_date='$appointment' 
				        AND facility='$facility_code'";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				if (!$results) {
					$sql = "SELECT patient_number_ccc as art_no,
					               UPPER(first_name)as first_name,
					               UPPER(other_name)as other_name,
					               UPPER(last_name)as last_name,
                                                       FLOOR(DATEDIFF('$today',dob)/365) as age,
					               IF(gender=1,'Male','Female')as gender,
					               UPPER(physical) as physical,
					               DATEDIFF('$today',nextappointment) as days_late, 
                                                       rst.name AS service_type
					        FROM patient 
	                               LEFT JOIN regimen_service_type rst 
	                               ON rst.id=patient.service
					        WHERE patient_number_ccc='$patient' 
					        AND facility_code='$facility_code'
					        AND DATEDIFF('$today',nextappointment)>0";
					$query = $this -> db -> query($sql);
					$results = $query -> result_array();
					if ($results){
						//select patient info
						foreach ($results as $result) {
							$patient_no = $result['art_no'];
							$patient_name = $result['first_name'] . " " . $result['other_name'] . " " . $result['last_name'];
							$service_type=$result['service_type'];
                                                        $age=$result['age'];
                                                        $gender = $result['gender'];
							$address = $result['physical'];
							$appointment = date('d-M-Y', strtotime($appointment));
							$days_late_by = $result['days_late'];
							$row_string .= "<tr><td>$patient_no</td><td>$patient_name</td><td>$service_type</td><td>$gender</td><td>$age</td><td>$address</td><td>$appointment</td><td>$days_late_by</td></tr>";
						}
					}
					$overall_total++;
				}
			}
		} else {
			echo "<tr><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>";
		}
		$row_string .= "</tbody></table>";

		//Overall Total
		$data['overall_total'] = $overall_total;
		$data['from'] = date('d-M-Y', strtotime($from));
		$data['to'] = date('d-M-Y', strtotime($to));
		$data['dyn_table'] = $row_string;
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "visiting_patient_report_row";
		$data['selected_report_type'] = "Patients Missing Appointments";
		$data['report_title'] = "Patients Missing Appointments";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/patients_missing_appointments_v';
		$this -> load -> view('template', $data);

	}

	public function getPatientsStartedonDate($from = "", $to = "") {
		//Variables
                $today=date('Y-m-d');
		$overall_total = 0;
		$facility_code = $this -> session -> userdata("facility");
		$from = date('Y-m-d', strtotime($from));
		$to = date('Y-m-d', strtotime($to));

		$sql = "SELECT p.patient_number_ccc as art_no,UPPER(p.first_name) as first_name,UPPER(p.last_name) as last_name,UPPER(p.other_name)as other_name,FLOOR(DATEDIFF('$today',p.dob)/365) as age, p.dob, IF(p.gender=1,'Male','Female') as gender, p.weight, r.regimen_desc,r.regimen_code,p.start_regimen_date, t.name AS service_type, s.name AS supported_by 
				from patient p 
				LEFT JOIN regimen r ON p.start_regimen =r.id
				LEFT JOIN regimen_service_type t ON t.id = p.service
				LEFT JOIN supporter s ON s.id = p.supported_by
				WHERE p.start_regimen_date BETWEEN '$from' and '$to' and p.facility_code='$facility_code' 
				GROUP BY p.patient_number_ccc";
		
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		$row_string = "<table border='1' class='dataTables' width='100%'>
				<thead>
				<tr>
					<th> Patient No </th>
					<th> Type of Service </th>
					<th> Client Support </th>
					<th> Patient Name </th>
					<th> Sex</th>
                                        <th>Age</th>
					<th> Start Regimen Date </th>
					<th> Regimen </th>
					<th> Current Weight (Kg)</th>
				</tr>
				</thead>
				<tbody>";
		if ($results) {
			foreach ($results as $result) {
				$patient_no = $result['art_no'];
				$service_type = $result['service_type'];
				$supported_by = $result['supported_by'];
				$patient_name = $result['first_name'] . " " . $result['other_name'] . " " . $result['last_name'];
				$gender = $result['gender'];
                                $age = $result['age'];
				$start_regimen_date = date('d-M-Y', strtotime($result['start_regimen_date']));
				$regimen_desc = "<b>" . $result['regimen_code'] . "</b>|" . $result['regimen_desc'];
				$weight = number_format($result['weight'], 2);
				$row_string .= "<tr><td>$patient_no</td><td>$service_type</td><td>$supported_by</td><td>$patient_name</td><td>$gender</td><td>$age</td><td>$start_regimen_date</td><td>$regimen_desc</td><td>$weight</td></tr>";
				$overall_total++;
			}

		} else {
			//$row_string .= "<tr><td colspan='8'>No Data Available</td></tr>";
		}
		$row_string .= "</tbody></table>";
		$data['from'] = date('d-M-Y', strtotime($from));
		$data['to'] = date('d-M-Y', strtotime($to));
		$data['dyn_table'] = $row_string;
		$data['all_count'] = $overall_total;
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "visiting_patient_report_row";
		$data['selected_report_type'] = "Visiting Patients";
		$data['report_title'] = "List of Patients Scheduled to Visit";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/patients_started_on_date_v';
		$this -> load -> view('template', $data);

	}

	public function getPatientsforRefill($from = "", $to = "") {
		//Variables
		$overall_total = 0;
		$today = date('Y-m-d');
		$facility_code = $this -> session -> userdata("facility");
		$from = date('Y-m-d', strtotime($from));
		$to = date('Y-m-d', strtotime($to));


        $sql = "SELECT 
				pv.patient_id as art_no,
				pv.dispensing_date, 
				t.name AS service_type,
				s.name AS supported_by,
				UPPER(p.first_name) as first_name ,
				UPPER(p.other_name) as other_name ,
				UPPER(p.last_name)as last_name,
				FLOOR(DATEDIFF('$today',p.dob)/365) as age,
				pv.current_weight as weight, 
				IF(p.gender=1,'Male','Female')as gender,
				r.regimen_desc,
				r.regimen_code,
				AVG(pv.adherence) as avg_adherence 
				FROM patient_visit pv 
				LEFT JOIN patient p ON p.patient_number_ccc=pv.patient_id
				LEFT JOIN visit_purpose v ON v.id=pv.visit_purpose
				LEFT JOIN supporter s ON s.id=p.supported_by
				LEFT JOIN regimen r ON r.id=p.current_regimen
				LEFT JOIN regimen_service_type t ON t.id=p.service
				LEFT JOIN patient_status ps ON ps.id=p.current_status
				WHERE pv.dispensing_date 
				BETWEEN '$from' 
				AND '$to' 
				AND v.name like '%routine%' 
				AND ps.name LIKE '%active%' 
				AND pv.facility = '$facility_code' 
				GROUP BY pv.patient_id,pv.dispensing_date";

		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		$row_string = "<table border='1'   class='dataTables'>
			<thead>
			<tr>
				<th> Patient No </th>
				<th> Type of Service </th>
				<th> Client Support </th>
				<th> Patient Name </th>
				<th> Current Age </th>
				<th> Sex</th>
				<th> Regimen </th>
				<th> Visit Date</th>
				<th> Current Weight (Kg) </th>
				<th> Average Adherence </th>
			</tr>
			</thead>
			<tbody>";
		if ($results) {
			foreach ($results as $result) {
				$patient_no = $result['art_no'];
				$service_type = $result['service_type'];
				$supported_by = $result['supported_by'];
				$patient_name = $result['first_name'] . " " . $result['other_name'] . " " . $result['last_name'];
				$age = $result['age'];
				$gender = $result['gender'];
				$dispensing_date = date('d-M-Y', strtotime($result['dispensing_date']));
				$regimen_desc = "<b>" . $result['regimen_code'] . "</b>|" . $result['regimen_desc'];
				$weight = $result['weight'];
				$avg_adherence = number_format($result['avg_adherence'], 2);
				$row_string .= "<tr><td>$patient_no</td><td>$service_type</td><td>$supported_by</td><td>$patient_name</td><td>$age</td><td>$gender</td><td>$regimen_desc</td><td>$dispensing_date</td><td>$weight</td><td>$avg_adherence</td></tr>";
				$overall_total++;
			}

		} else {
			//$row_string .= "<tr><td colspan='6'>No Data Available</td></tr>";
		}
		$row_string .= "</tbody></table>";
		$data['from'] = date('d-M-Y', strtotime($from));
		$data['to'] = date('d-M-Y', strtotime($to));
		$data['dyn_table'] = $row_string;
		$data['all_count'] = $overall_total;
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "visiting_patient_report_row";
		$data['selected_report_type'] = "Visiting Patients";
		$data['report_title'] = "List of Patients Visited For Refill";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/patients_for_refill_v';
		$this -> load -> view('template', $data);
	}

	public function getStartedonART($from = "", $to = "", $supported_by = 0) {
		//Variables
		$patient_total = 0;
		$facility_code = $this -> session -> userdata("facility");
		$supported_query = "and facility_code='$facility_code'";
		$from = date('Y-m-d', strtotime($from));
		$to = date('Y-m-d', strtotime($to));
		$regimen_totals = array();
		$overall_child_male = 0;
		$overall_child_female = 0;
		$overall_adult_male = 0;
		$overall_adult_female = 0;

		$overall_adult_male_art = 0;
		$overall_adult_male_pep = 0;
		$overall_adult_male_oi = 0;

		$overall_adult_female_art = 0;
		$overall_adult_female_pep = 0;
		$overall_adult_female_pmtct = 0;
		$overall_adult_female_oi = 0;

		$overall_child_male_art = 0;
		$overall_child_male_pep = 0;
		$overall_child_male_pmtct = 0;
		$overall_child_male_oi = 0;

		$overall_child_female_art = 0;
		$overall_child_female_pep = 0;
		$overall_child_female_pmtct = 0;
		$overall_child_female_oi = 0;

		if ($supported_by == 1) {
			$supported_query = "and supported_by=1";
		} else if ($supported_by == 2) {
			$supported_query = "and supported_by=2";
		}

		//Get Patient Totals
		$sql = "select count(*) as total 
					from patient p,gender g,regimen_service_type rs,regimen r,patient_status ps 
					where start_regimen_date between '$from' and '$to' and 
					p.gender=g.id and p.service=rs.id and p.start_regimen=r.id 
					and ps.id=p.current_status and ps.name LIKE '%active%'
					and rs.name LIKE '%art%' and p.facility_code='$facility_code'";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		$source_total = $results[0]['total'];
		$total = $source_total;
		$other_total=0;
		//Get Totals for each regimen
		$sql = "select count(*) as total, r.regimen_desc,r.regimen_code,p.start_regimen from patient p,gender g,regimen_service_type rs,regimen r where start_regimen_date between '$from' and '$to' and p.gender=g.id and p.service=rs.id and p.start_regimen=r.id and rs.name LIKE '%art%' and p.facility_code='$facility_code' group by p.start_regimen ORDER BY r.regimen_code ASC";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		$row_string = "<table border='1'  cellpadding='5' class='dataTables'>
			<thead>
			<tr>
				<th ></th>
				<th >Total</th><th></th>
				<th> Adult</th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th>
				<th> Children </th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th>
			</tr>
			<tr>
				<th></th>
				<th ></th>
				<th ></th>
				<th>Male</th><th></th><th></th><th></th><th></th><th></th>
				<th>Female</th><th></th><th></th><th></th><th></th><th></th><th></th><th></th>
				<th>Male</th><th></th><th></th><th></th><th></th><th></th><th></th><th></th>
				<th>Female</th><th></th><th></th><th></th><th></th><th></th><th></th><th></th>
			</tr>
			<tr>
				<th></th>
				<th></th>
				<th></th>
				<th >ART</th><th></th>
				<th >PEP</th><th></th>
				<th >OI</th><th></th>
				<th >ART</th><th></th>
				<th >PEP</th><th></th>
				<th >PMTCT</th><th></th>
				<th >OI</th><th></th>
				<th >ART</th><th></th>
				<th >PEP</th><th></th>
				<th >PMTCT</th><th></th>
				<th >OI</th><th></th>
				<th >ART</th><th></th>
				<th >PEP</th><th></th>
				<th >PMTCT</th><th></th>
				<th >OI</th><th></th>
			</tr>
			<tr>
				<th>Regimen</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
			</tr>
			</thead><tbody>";
			if($source_total==0){
				$source_total=1;
			}
		if ($results) {
			foreach ($results as $result) {
				$regimen_totals[$result['start_regimen']] = $result['total'];
				$start_regimen = $result['start_regimen'];
				$regimen_name = $result['regimen_desc'];
				$regimen_code = $result['regimen_code'];
				$regimen_total = $result['total'];
				$other_total+=$regimen_total;
				$regimen_total_percentage = number_format(($regimen_total / $source_total) * 100, 1);
				$row_string .= "<tr><td><b>$regimen_code</b> | $regimen_name</td><td>$regimen_total</td><td>$regimen_total_percentage</td>";
				//SQL for Adult Male Regimens
				$sql = "select count(*) as total_adult_male, r.regimen_desc,r.regimen_code,p.start_regimen,p.service,rs.name as service_name from patient p,gender g,regimen_service_type rs,regimen r where start_regimen_date between '$from' and '$to' and p.gender=g.id and p.service=rs.id and p.start_regimen=r.id and FLOOR(datediff('$to',p.dob)/365)>15 and p.gender='1' and start_regimen='$start_regimen' and p.service='1' and p.facility_code='$facility_code' group by p.start_regimen,p.service";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				$total_adult_male_art = "-";
				$total_adult_male_pep = "-";
				$total_adult_male_oi = "-";
				$total_adult_male_art_percentage = "-";
				$total_adult_male_pep_percentage = "-";
				$total_adult_male_oi_percentage = "-";
				
				if ($results) {
					foreach ($results as $result) {
						$total_adult_male = $result['total_adult_male'];
						$overall_adult_male += $total_adult_male;
						$service_name = $result['service_name'];
						if ($service_name == "ART") {
							$overall_adult_male_art += $total_adult_male;
							$total_adult_male_art = number_format($total_adult_male);
							$total_adult_male_art_percentage = number_format(($total_adult_male / $source_total) * 100, 1);
						} else if ($service_name == "PEP") {
							$overall_adult_male_pep += $total_adult_male;
							$total_adult_male_pep = number_format($total_adult_male);
							$total_adult_male_pep_percentage = number_format(($total_adult_male_pep / $source_total) * 100, 1);
						} else if ($service_name == "OI Only") {
							$overall_adult_male_oi += $total_adult_male;
							$total_adult_male_oi = number_format($total_adult_male);
							$total_adult_male_oi_percentage = number_format(($total_adult_male_oi / $source_total) * 100, 1);
						}

					}
					if ($result['start_regimen'] != null) {
						$row_string .= "<td>$total_adult_male_art</td><td>$total_adult_male_art_percentage</td><td>$total_adult_male_pep</td><td>$total_adult_male_pep_percentage</td><td>$total_adult_male_oi</td><td>$total_adult_male_oi_percentage</td>";
					}
				} else {
					$row_string .= "<td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td>";

				}

				//SQL for Adult Female Regimens
				$sql = "select count(*) as total_adult_female, r.regimen_desc,r.regimen_code,p.start_regimen,p.service,rs.name as service_name from patient p,gender g,regimen_service_type rs,regimen r where start_regimen_date between '$from' and '$to' and p.gender=g.id and p.service=rs.id and p.start_regimen=r.id and FLOOR(datediff('$to',p.dob)/365)>15 and p.gender='2' and p.service='1' and start_regimen='$start_regimen' and p.facility_code='$facility_code' group by p.start_regimen,p.service";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				$total_adult_female_art = "-";
				$total_adult_female_pep = "-";
				$total_adult_female_pmtct = "-";
				$total_adult_female_oi = "-";
				$total_adult_female_art_percentage = "-";
				$total_adult_female_pep_percentage = "-";
				$total_adult_female_pmtct_percentage = "-";
				$total_adult_female_oi_percentage = "-";

				if ($results) {
					foreach ($results as $result) {
						$total_adult_female = $result['total_adult_female'];
						$overall_adult_female += $total_adult_female;
						$service_name = $result['service_name'];
						if ($service_name == "ART") {
							$overall_adult_female_art += $total_adult_female;
							$total_adult_female_art = number_format($total_adult_female);
							$total_adult_female_art_percentage = number_format(($total_adult_female / $source_total) * 100, 1);
						} else if ($service_name == "PEP") {
							$overall_adult_female_pep += $total_adult_female;
							$total_adult_female_pep = number_format($total_adult_female);
							$total_adult_female_pep_percentage = number_format(($total_adult_female_pep / $source_total) * 100, 1);
						} else if ($service_name == "PMTCT") {
							$overall_adult_female_pmtct += $total_adult_female;
							$total_adult_female_pmtct = number_format($total_adult_female);
							$total_adult_female_pmtct_percentage = number_format(($total_adult_female_pmtct / $source_total) * 100, 1);
						} else if ($service_name == "OI Only") {
							$overall_adult_female_oi += $total_adult_female;
							$total_adult_female_oi = number_format($total_adult_female);
							$total_adult_female_oi_percentage = number_format(($total_adult_female_oi / $source_total) * 100, 1);
						}
					}
					if ($result['start_regimen'] != null) {
						$row_string .= "<td>$total_adult_female_art</td><td>$total_adult_female_art_percentage</td><td>$total_adult_female_pep</td><td>$total_adult_female_pep_percentage</td><td>$total_adult_female_pmtct</td><td>$total_adult_female_pmtct_percentage</td><td>$total_adult_female_oi</td><td>$total_adult_female_oi_percentage</td>";
					}
				} else {
					$row_string .= "<td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td>";
				}
				//SQL for Child Male Regimens
				$sql = "select count(*) as total_child_male, r.regimen_desc,r.regimen_code,p.start_regimen,p.service,rs.name as service_name from patient p,gender g,regimen_service_type rs,regimen r where start_regimen_date between '$from' and '$to' and p.gender=g.id and p.service=rs.id and p.start_regimen=r.id and FLOOR(datediff('$to',p.dob)/365)<=15 and p.gender='1' and p.service='1' and start_regimen='$start_regimen' and p.facility_code='$facility_code' group by p.start_regimen,p.service";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				$total_child_male_art = "-";
				$total_child_male_pep = "-";
				$total_child_male_pmtct = "-";
				$total_child_male_oi = "-";
				$total_child_male_art_percentage = "-";
				$total_child_male_pep_percentage = "-";
				$total_child_male_pmtct_percentage = "-";
				$total_child_male_oi_percentage = "-";
				if ($results) {
					foreach ($results as $result) {
						$total_child_male = $result['total_child_male'];
                                                $service_name = $result['service_name'];
						$overall_child_male += $total_child_male;
						if ($service_name == "ART") {
							$overall_child_male_art += $total_child_male;
							$total_child_male_art = number_format($total_child_male);
							$total_child_male_art_percentage = number_format(($total_child_male / $source_total) * 100, 1);
						} else if ($service_name == "PEP") {
							$overall_child_male_pep += $total_child_male;
							$total_child_male_pep = number_format($total_child_male);
							$total_child_male_pep_percentage = number_format(($total_child_male_pep / $source_total) * 100, 1);
						} else if ($service_name == "PMTCT") {
							$overall_child_male_pmtct += $total_child_male;
							$total_child_male_pmtct = number_format($total_child_male);
							$total_child_male_pmtct_percentage = number_format(($total_child_male_pmtct / $source_total) * 100, 1);
						} else if ($service_name == "OI Only") {
							$overall_child_male_oi += $total_child_male;
							$total_child_male_oi = number_format($total_child_male);
							$total_child_male_oi_percentage = number_format(($total_child_male_oi / $source_total) * 100, 1);
						}

					}
					if ($result['start_regimen'] != null) {
						$row_string .= "<td>$total_child_male_art</td><td>$total_child_male_art_percentage</td><td>$total_child_male_pep</td><td>$total_child_male_pep_percentage</td><td>$total_child_male_pmtct</td><td>$total_child_male_pmtct_percentage</td><td>$total_child_male_oi</td><td>$total_child_male_oi_percentage</td>";
					}
				} else {
					$row_string .= "<td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td>";
				}
				//SQL for Child Female Regimens
				$sql = "select count(*) as total_child_female, r.regimen_desc,r.regimen_code,p.start_regimen,p.service,rs.name as service_name from patient p,gender g,regimen_service_type rs,regimen r where start_regimen_date between '$from' and '$to' and p.gender=g.id and p.service=rs.id and p.start_regimen=r.id and FLOOR(datediff('$to',p.dob)/365)<=15 and p.gender='2' and p.service='1' and start_regimen='$start_regimen' and p.facility_code='$facility_code' group by p.start_regimen,p.service";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				$total_child_female_art = "-";
				$total_child_female_pep = "-";
				$total_child_female_pmtct = "-";
				$total_child_female_oi = "-";
				$total_child_female_art_percentage = "-";
				$total_child_female_pep_percentage = "-";
				$total_child_female_pmtct_percentage = "-";
				$total_child_female_oi_percentage = "-";
				if ($results) {
					foreach ($results as $result) {
						$total_child_female = $result['total_child_female'];
						$overall_child_female += $total_child_female;
						if ($service_name == "ART") {
							$overall_child_female_art += $total_child_female;
							$total_child_female_art = number_format($total_child_female);
							$total_child_female_art_percentage = number_format(($total_child_female / $source_total) * 100, 1);
						} else if ($service_name == "PEP") {
							$overall_child_female_pep += $total_child_female;
							$total_child_female_pep = number_format($total_child_female);
							$total_child_female_pep_percentage = number_format(($total_child_female_pep / $source_total) * 100, 1);
						} else if ($service_name == "PMTCT") {
							$overall_child_female_pmtct += $total_child_female;
							$total_child_female_pmtct = number_format($total_child_female);
							$total_child_female_pmtct_percentage = number_format(($total_child_female_pmtct / $source_total) * 100, 1);
						} else if ($service_name == "OI Only") {
							$overall_child_female_oi += $total_child_female;
							$total_child_female_oi = number_format($total_child_female);
							$total_child_female_oi_percentage = number_format(($total_child_female_oi / $source_total) * 100, 1);
						}

					}
					if ($result['start_regimen'] != null) {
						$row_string .= "<td>$total_child_female_art</td><td>$total_child_female_art_percentage</td><td>$total_child_female_pep</td><td>$total_child_female_pep_percentage</td><td>$total_child_female_pmtct</td><td>$total_child_female_pmtct_percentage</td><td>$total_child_female_oi</td><td>$total_child_female_oi_percentage</td>";
					}
				} else {
					$row_string .= "<td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td>";
				}
				$row_string .= "</tr>";
			}
			if($total==0){
				$total=1;
			}
			$overall_art_male_percent = number_format(($overall_adult_male_art / $total) * 100, 1);
			$overall_pep_male_percent = number_format(($overall_adult_male_pep / $total) * 100, 1);
			$overall_oi_male_percent = number_format(($overall_adult_male_oi / $total) * 100, 1);

			$overall_art_female_percent = number_format(($overall_adult_female_art / $total) * 100, 1);
			$overall_pep_female_percent = number_format(($overall_adult_female_pep / $total) * 100, 1);
			$overall_pmtct_female_percent = number_format(($overall_adult_female_pmtct / $total) * 100, 1);
			$overall_oi_female_percent = number_format(($overall_adult_female_oi / $total) * 100, 1);

			$overall_art_childmale_percent = number_format(($overall_child_male_art / $total) * 100, 1);
			$overall_pep_childmale_percent = number_format(($overall_child_male_pep / $total) * 100, 1);
			$overall_oi_childmale_percent = number_format(($overall_child_male_pmtct / $total) * 100, 1);
			$overall_pmtct_childmale_percent = number_format(($overall_child_male_oi / $total) * 100, 1);

			$overall_art_childfemale_percent = number_format(($overall_child_female_art / $total) * 100, 1);
			$overall_pep_childfemale_percent = number_format(($overall_child_female_pep / $total) * 100, 1);
			$overall_pmtct_childfemale_percent = number_format(($overall_child_female_pmtct / $total) * 100, 1);
			$overall_oi_childfemale_percent = number_format(($overall_child_female_oi / $total) * 100, 1);

			$row_string .= "</tbody><tfoot><tr><td>TOTALS</td><td>$other_total</td><td>100</td><td>$overall_adult_male_art</td><td>$overall_art_male_percent</td><td>$overall_adult_male_pep</td><td>$overall_pep_male_percent</td><td>$overall_adult_male_oi</td><td>$overall_oi_male_percent</td><td>$overall_adult_female_art</td><td>$overall_art_female_percent</td><td>$overall_adult_female_pep</td><td>$overall_pep_female_percent</td><td>$overall_adult_female_pmtct</td><td>$overall_pmtct_female_percent</td><td>$overall_adult_female_oi</td><td>$overall_oi_female_percent</td><td>$overall_child_male_art</td><td>$overall_art_childmale_percent</td><td>$overall_child_male_pep</td><td>$overall_pep_childmale_percent</td><td>$overall_child_male_pmtct</td><td>$overall_pmtct_childmale_percent</td><td>$overall_child_male_oi</td><td>$overall_oi_childmale_percent</td><td>$overall_child_female_art</td><td>$overall_art_childfemale_percent</td><td>$overall_child_female_pep</td><td>$overall_pep_childfemale_percent</td><td>$overall_child_female_pmtct</td><td>$overall_pmtct_childfemale_percent</td><td>$overall_child_female_oi</td><td>$overall_oi_childfemale_percent</td></tr></tfoot></table>";
			$row_string .= "</tfoot></table>";
		} else {
			$row_string = "<h4 style='text-align: center'><span >No Data Available</span></h4>";
		}

		$data['from'] = date('d-M-Y', strtotime($from));
		$data['to'] = date('d-M-Y', strtotime($to));
		$data['dyn_table'] = $row_string;
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "standard_report_row";
		$data['selected_report_type'] = "Standard Reports";
		$data['report_title'] = "Number of Patients Started on ART in the Period";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/patients_started_on_art_v';
		$this -> load -> view('template', $data);
	}

	public function patient_active_byregimen($from = "2013-06-06") {
		//Variables
		$facility_code = $this -> session -> userdata("facility");
		$from = date('Y-m-d', strtotime($from));
		$regimen_totals = array();
		$data = array();
		$row_string = "";
		$overall_adult_male = 0;
		$overall_adult_female = 0;
		$overall_child_male = 0;
		$overall_child_female = 0;

		//Get Total of all patients
		$sql = "SELECT count(*) as total, r.regimen_desc,p.current_regimen FROM patient p,regimen r WHERE p.date_enrolled<='$from' AND p.current_status=1 AND r.id=p.current_regimen AND p.facility_code='$facility_code' AND p.current_regimen !=0 AND p.current_regimen !='' AND p.current_status !='' AND p.current_status !=0 and p.active='1'";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		$patient_total = $results[0]['total'];

		//Get Totals for each regimen
		$sql = "SELECT count(*) as total, r.regimen_desc,r.regimen_code,p.current_regimen FROM patient p,regimen r WHERE p.date_enrolled<='$from' AND p.current_status=1 AND r.id=p.current_regimen AND p.facility_code='$facility_code' AND p.current_regimen !=0 AND p.current_regimen !='' AND p.current_status !='' AND p.current_status !=0 and p.active='1' GROUP BY p.current_regimen ORDER BY r.regimen_code ASC";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			$row_string .= "<table border='1'  cellpadding='5' class='dataTables'>
			<thead>
			<tr>
				<th ></th>
				<th >Total</th><th></th>
				<th> Adult</th><th></th><th></th><th></th>
				<th> Children </th><th></th><th></th><th></th>
			</tr>
			<tr>
				<th></th>
				<th></th>
				<th></th>
				<th>Male</th><th></th>
				<th>Female</th><th></th>
				<th>Male</th><th></th>
				<th>Female</th><th></th>
			</tr>
			<tr>
				<th>Regimen</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th><th>No.</th>
				<th>%</th><th>No.</th>
				<th>%</th>
			</tr></thead><tbody>";
			foreach ($results as $result) {
				$regimen_totals[$result['current_regimen']] = $result['total'];
				$current_regimen = $result['current_regimen'];
				$regimen_name = $result['regimen_desc'];
				$regimen_code = $result['regimen_code'];
				$regimen_total = $result['total'];
				$regimen_total_percentage = number_format(($regimen_total / $patient_total) * 100, 1);
				$row_string .= "<tr><td><b>$regimen_code</b> | $regimen_name</td><td>$regimen_total</td><td>$regimen_total_percentage</td>";
				//SQL for Adult Male Regimens
				$sql = "SELECT count(*) as total_adult_male, r.regimen_desc,p.current_regimen as regimen_id FROM patient p,regimen r WHERE p.date_enrolled<='$from' AND p.current_status=1 AND r.id=p.current_regimen AND p.facility_code='$facility_code' AND p.gender=1 AND p.current_regimen='$current_regimen' AND FLOOR(datediff('$from',p.dob)/365)>15  and p.active='1' GROUP BY p.current_regimen";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				if ($results) {
					foreach ($results as $result) {
						$total_adult_male = $result['total_adult_male'];
						$overall_adult_male += $total_adult_male;
						$total_adult_male_percentage = number_format(($total_adult_male / $regimen_total) * 100, 1);
						if ($result['regimen_id'] != null) {
							$row_string .= "<td>$total_adult_male</td><td>$total_adult_male_percentage</td>";
						}
					}
				} else {
					$row_string .= "<td>-</td><td>-</td>";
				}
				//SQL for Adult Female Regimens
				$sql = "SELECT count(*) as total_adult_female, r.regimen_desc,p.current_regimen as regimen_id FROM patient p,regimen r WHERE p.date_enrolled<='$from' AND p.current_status=1 AND r.id=p.current_regimen AND p.facility_code='$facility_code' AND p.gender=2 AND p.current_regimen='$current_regimen' AND FLOOR(datediff('$from',p.dob)/365)>15 and p.active='1' GROUP BY p.current_regimen";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				if ($results) {
					foreach ($results as $result) {
						$total_adult_female = $result['total_adult_female'];
						$overall_adult_female += $total_adult_female;
						$total_adult_female_percentage = number_format(($total_adult_female / $regimen_total) * 100, 1);
						if ($result['regimen_id'] != null) {
							$row_string .= "<td>$total_adult_female</td><td>$total_adult_female_percentage</td>";
						}
					}
				} else {
					$row_string .= "<td>-</td><td>-</td>";
				}
				//SQL for Child Male Regimens
				$sql = "SELECT count(*) as total_child_male, r.regimen_desc,p.current_regimen as regimen_id FROM patient p,regimen r WHERE p.date_enrolled<='$from' AND p.current_status=1 AND r.id=p.current_regimen AND p.facility_code='$facility_code' AND p.gender=1 AND p.current_regimen='$current_regimen' AND FLOOR(datediff('$from',p.dob)/365)<=15 and p.active='1' GROUP BY p.current_regimen";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				if ($results) {
					foreach ($results as $result) {
						$total_child_male = $result['total_child_male'];
						$overall_child_male += $total_child_male;
						$total_child_male_percentage = number_format(($total_child_male / $regimen_total) * 100, 1);
						if ($result['regimen_id'] != null) {
							$row_string .= "<td>$total_child_male</td><td>$total_child_male_percentage</td>";
						}
					}
				} else {
					$row_string .= "<td>-</td><td>-</td>";
				}
				//SQL for Child Female Regimens
				$sql = "SELECT count(*) as total_child_female, r.regimen_desc,p.current_regimen as regimen_id FROM patient p,regimen r WHERE p.date_enrolled<='$from' AND p.current_status=1 AND r.id=p.current_regimen AND p.facility_code='$facility_code' AND p.gender=2 AND p.current_regimen='$current_regimen' AND FLOOR(datediff('$from',p.dob)/365)<=15 and p.active='1' GROUP BY p.current_regimen";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				if ($results) {
					foreach ($results as $result) {
						$total_child_female = $result['total_child_female'];
						$overall_child_female += $total_child_female;
						$total_child_female_percentage = number_format(($total_child_female / $regimen_total) * 100, 1);
						if ($result['regimen_id'] != null) {
							$row_string .= "<td>$total_child_female</td><td>$total_child_female_percentage</td>";
						}
					}
				} else {
					$row_string .= "<td>-</td><td>-</td>";
				}
				$row_string .= "</tr>";
			}
			$row_string .= "</tbody><tfoot><tr><td><b>Totals:</b></td><td><b>$patient_total</b></td><td><b>100</b></td><td><b>$overall_adult_male</b></td><td><b>" . number_format(($overall_adult_male / $patient_total) * 100, 1) . "</b></td><td><b>$overall_adult_female</b></td><td><b>" . number_format(($overall_adult_female / $patient_total) * 100, 1) . "</b></td><td><b>$overall_child_male</b></td><td><b>" . number_format(($overall_child_male / $patient_total) * 100, 1) . "</b></td><td><b>$overall_child_female</b></td><td><b>" . number_format(($overall_child_female / $patient_total) * 100, 1) . "</b></td></tr>";
			$row_string .= "</tfoot></table>";

		}
		$data['from'] = date('d-M-Y', strtotime($from));
		$data['dyn_table'] = $row_string;
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "standard_report_row";
		$data['selected_report_type'] = "Standard Reports";
		$data['report_title'] = "Number of Active Patients Receiving ART (by Regimen)";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/no_of_patients_receiving_art_byregimen_v';
		$this -> load -> view('template', $data);
	}

	public function cumulative_patients($from = "2013-06-06", $type = '1') {
		//Variables
		$facility_code = $this -> session -> userdata("facility");
		$from = date('Y-m-d', strtotime($from));
		$status_totals = array();
		$row_string = "";
		$total_adult_male_art = 0;
		$total_adult_male_pep = 0;
		$total_adult_male_oi = 0;
		$total_adult_female_art = 0;
		$total_adult_female_pep = 0;
		$total_adult_female_pmtct = 0;
		$total_adult_female_oi = 0;
		$total_child_male_art = 0;
		$total_child_male_pep = 0;
		$total_child_male_pmtct = 0;
		$total_child_male_oi = 0;
		$total_child_female_art = 0;
		$total_child_female_pep = 0;
		$total_child_female_pmtct = 0;
		$total_child_female_oi = 0;

		//Get Total Count of all patients
		$sql = "select count(*) as total from patient p,patient_status ps,regimen_service_type rst,gender g where(p.date_enrolled <= '$from' or p.date_enrolled='') and ps.id=p.current_status and p.service=rst.id and p.gender=g.id and facility_code='$facility_code' and p.active='1'";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		$patient_total = $results[0]['total'];

		$row_string = "<table border='1' cellpadding='5' id='tblcumulpatients' class='dataTables' >
			<thead><tr>
				<th style='width:15%;'>Current Status</th>
				<th>Total</th><th>Total</th>
				<th > Adult</th><th></th><th></th><th></th><th></th><th></th><th></th>
				<th > Children </th><th></th><th></th><th></th><th></th><th></th><th></th><th></th>
			</tr>
			<tr>
				<th>-</th>
				<th >No.</th>
				<th >%</th>
				<th >Male</th><th></th><th></th>
				<th >Female</th><th></th><th></th><th></th>
				<th >Male</th><th></th><th></th><th></th>
				<th >Female</th><th></th><th></th><th></th>
			</tr>
			<tr>
				<th></th>
				<th></th>
				<th></th>
				<th>ART</th>
				<th>PEP</th>
				<th>OI</th>
				<th>ART</th>
				<th>PEP</th>
				<th>PMTCT</th>
				<th>OI</th>
				<th>ART</th>
				<th>PEP</th>
				<th>PMTCT</th>
				<th>OI</th>
				<th>ART</th>
				<th>PEP</th>
				<th>PMTCT</th>
				<th>OI</th>
			</tr></thead><tbody>";

		//Get Totals for each Status
		//$sql = "select count(p.id) as total,current_status,ps.name from patient p,patient_status ps where(date_enrolled <= '$from' or date_enrolled='') and facility_code='$facility_code' and ps.id = current_status and current_status!='' and service!='' and gender !='' group by p.current_status";
		$sql = "select count(p.id) as total,p.current_status,ps.name from patient p,patient_status ps,regimen_service_type rst,gender g where(p.date_enrolled <= '$from' or p.date_enrolled='') and ps.id=p.current_status and p.service=rst.id and p.gender=g.id and facility_code='$facility_code' and p.active='1' group by p.current_status";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {

			foreach ($results as $result) {
				$status_totals[$result['current_status']] = $result['total'];
				$current_status = $result['current_status'];
				$status_name = $result['name'];
				$patient_percentage = number_format(($status_totals[$current_status] / $patient_total) * 100, 1);
				$row_string .= "<tr><td>$status_name</td><td>$status_totals[$current_status]</td><td>$patient_percentage</td>";
				//SQL for Adult Male Status
				$service_list = array('ART', 'PEP', 'OI Only');
				$sql = "SELECT count(*) as total_adult_male, ps.Name,ps.id as current_status,r.name AS Service FROM patient p,patient_status ps,regimen_service_type r WHERE  p.current_status=ps.id AND p.service=r.id AND p.current_status='$current_status' AND p.facility_code='$facility_code' AND p.gender=1 AND p.service !=3 AND FLOOR(datediff('$from',p.dob)/365)>15 and p.active='1' GROUP BY service";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				$i = 0;
				$j = 0;
				if ($results) {
					while ($j < 3) {
						$patient_current_total = @$results[$i]['total_adult_male'];
						$service = @$results[$i]['Service'];
						if ($service == @$service_list[$j]) {
							$row_string .= "<td>$patient_current_total</td>";
							if ($service == "ART") {
								$total_adult_male_art += $patient_current_total;
							} else if ($service == "PEP") {
								$total_adult_male_pep += $patient_current_total;
							} else if ($service == "OI Only") {
								$total_adult_male_oi += $patient_current_total;
							}
							$i++;
							$j++;
						} else {
							$row_string .= "<td>-</td>";
							$j++;
						}
					}

				} else {
					$row_string .= "<td>-</td><td>-</td><td>-</td>";
				}
				//SQL for Adult Female Status
				$service_list = array('ART', 'PEP', 'PMTCT', 'OI Only');
				$sql = "SELECT count(*) as total_adult_female, ps.Name,ps.id as current_status,r.name AS Service FROM patient p,patient_status ps,regimen_service_type r WHERE  p.current_status=ps.id AND p.service=r.id AND p.current_status='$current_status' AND p.facility_code='$facility_code' AND p.gender=2  AND FLOOR(datediff('$from',p.dob)/365)>15 and p.active='1' GROUP BY service";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				$i = 0;
				$j = 0;
				if ($results) {
					while ($j < 4) {
						$patient_current_total = @$results[$i]['total_adult_female'];
						$service = @$results[$i]['Service'];
						if ($service == @$service_list[$j]) {
							$row_string .= "<td>$patient_current_total</td>";
							if ($service == "ART") {
								$total_adult_female_art += $patient_current_total;
							} else if ($service == "PEP") {
								$total_adult_female_pep += $patient_current_total;
							} else if ($service == "PMTCT") {
								$total_adult_female_pmtct += $patient_current_total;
							} else if ($service == "OI Only") {
								$total_adult_female_oi += $patient_current_total;
							}
							$i++;
							$j++;
						} else {
							$row_string .= "<td>-</td>";
							$j++;
						}
					}
				} else {
					$row_string .= "<td>-</td><td>-</td><td>-</td><td>-</td>";
				}
				//SQL for Child Male Status
				$service_list = array('ART', 'PEP', 'PMTCT', 'OI Only');
				$sql = "SELECT count(*) as total_child_male, ps.Name,ps.id as current_status,r.name AS Service FROM patient p,patient_status ps,regimen_service_type r WHERE  p.current_status=ps.id AND p.service=r.id AND p.current_status='$current_status' AND p.facility_code='$facility_code' AND p.gender=1  AND FLOOR(datediff('$from',p.dob)/365)<=15 and p.active='1' GROUP BY service";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				$i = 0;
				$j = 0;
				if ($results) {
					while ($j < 4) {
						$patient_current_total = @$results[$i]['total_child_male'];
						$service = @$results[$i]['Service'];
						if ($service == @$service_list[$j]) {
							$row_string .= "<td>$patient_current_total</td>";
							if ($service == "ART") {
								$total_child_male_art += $patient_current_total;
							} else if ($service == "PEP") {
								$total_child_male_pep += $patient_current_total;
							} else if ($service == "PMTCT") {
								$total_child_male_pmtct += $patient_current_total;
							} else if ($service == "OI Only") {
								$total_child_male_oi += $patient_current_total;
							}
							$i++;
							$j++;
						} else {
							$row_string .= "<td>-</td>";
							$j++;
						}
					}
				} else {
					$row_string .= "<td>-</td><td>-</td><td>-</td><td>-</td>";
				}
				//SQL for Child Female Status
				$service_list = array('ART', 'PEP', 'PMTCT', 'OI Only');
				$sql = "SELECT count(*) as total_child_female, ps.Name,ps.id as current_status,r.name AS Service FROM patient p,patient_status ps,regimen_service_type r WHERE  p.current_status=ps.id AND p.service=r.id AND p.current_status='$current_status' AND p.facility_code='$facility_code' AND p.gender=2  AND FLOOR(datediff('$from',p.dob)/365)<=15 and p.active='1' GROUP BY service";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				$i = 0;
				$j = 0;
				if ($results) {
					while ($j < 4) {
						$patient_current_total = @$results[$i]['total_child_female'];
						$service = @$results[$i]['Service'];
						if ($service == @$service_list[$j]) {
							$row_string .= "<td>$patient_current_total</td>";
							if ($service == "ART") {
								$total_child_female_art += $patient_current_total;
							} else if ($service == "PEP") {
								$total_child_female_pep += $patient_current_total;
							} else if ($service == "PMTCT") {
								$total_child_female_pmtct += $patient_current_total;
							} else if ($service == "OI Only") {
								$total_child_female_oi += $patient_current_total;
							}
							$i++;
							$j++;
						} else {
							$row_string .= "<td>-</td>";
							$j++;
						}
					}
				} else {
					$row_string .= "<td>-</td><td>-</td><td>-</td><td>-</td>";
				}
				$row_string .= "</tr>";
			}
			$row_string .= "</tbody><tfoot><tr class='tfoot'><td><b>Total:</b></td><td><b>$patient_total</b></td><td><b>100</b></td><td><b>$total_adult_male_art</b></td><td><b>$total_adult_male_pep</b></td><td><b>$total_adult_male_oi</b></td><td><b>$total_adult_female_art</b></td><td><b>$total_adult_female_pep</b></td><td><b>$total_adult_female_pmtct</b></td><td><b>$total_adult_female_oi</b></td><td><b>$total_child_male_art</b></td><td><b>$total_child_male_pep</b></td><td><b>$total_child_male_pmtct</b></td><td><b>$total_child_male_oi</b></td><td><b>$total_child_female_art</b></td><td><b>$total_child_female_pep</b></td><td><b>$total_child_female_pmtct</b></td><td><b>$total_child_female_oi</b></td></tr>";
			$row_string .= "</tfoot></table>";

		}
		$data['from'] = date('d-M-Y', strtotime($from));
		$data['dyn_table'] = $row_string;
		$data['title'] = "Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "standard_report_row";
		$data['selected_report_type'] = "Standard Reports";
		$data['report_title'] = "Cumulative Number of Patients to Date";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['repo_type'] = $type;
		$data['content_view'] = 'reports/cumulative_patients_v';
		if ($type == 1) {
			$this -> load -> view('template', $data);
		} else {
			$this -> load -> view('reports/cumulative_patients_v', $data);
		}

	}

	public function drug_consumption($year = "",$pack_unit="unit") {
		$data['year'] = $year;
		$facility_code = $this -> session -> userdata("facility");
		$facility_name = $this -> session -> userdata('facility_name');

		$data = array();
		$aColumns = array('drug', 'Unit');

		$iDisplayStart = $this -> input -> get_post('iDisplayStart', true);
		$iDisplayLength = $this -> input -> get_post('iDisplayLength', true);
		$iSortCol_0 = $this -> input -> get_post('iSortCol_0', false);
		$iSortingCols = $this -> input -> get_post('iSortingCols', true);
		$sSearch = $this -> input -> get_post('sSearch', true);
		$sEcho = $this -> input -> get_post('sEcho', true);

		$count = 0;

		// Paging
		if (isset($iDisplayStart) && $iDisplayLength != '-1') {
			$this -> db -> limit($this -> db -> escape_str($iDisplayLength), $this -> db -> escape_str($iDisplayStart));
		}

		// Ordering
		if (isset($iSortCol_0)) {
			for ($i = 0; $i < intval($iSortingCols); $i++) {
				$iSortCol = $this -> input -> get_post('iSortCol_' . $i, true);
				$bSortable = $this -> input -> get_post('bSortable_' . intval($iSortCol), true);
				$sSortDir = $this -> input -> get_post('sSortDir_' . $i, true);

				if ($bSortable == 'true') {
					$this -> db -> order_by($aColumns[intval($this -> db -> escape_str($iSortCol))], $this -> db -> escape_str($sSortDir));
				}
			}
		}
		/*
		 * Filtering
		 * NOTE this does not match the built-in DataTables filtering which does it
		 * word by word on any field. It's possible to do here, but concerned about efficiency
		 * on very large tables, and MySQL's regex functionality is very limited
		 */
		if (isset($sSearch) && !empty($sSearch)) {
			for ($i = 0; $i < count($aColumns); $i++) {
				$bSearchable = $this -> input -> get_post('bSearchable_' . $i, true);

				// Individual column filtering
				if (isset($bSearchable) && $bSearchable == 'true') {
					$this -> db -> or_like($aColumns[$i], $this -> db -> escape_like_str($sSearch));
				}
			}
		}

		// Select Data
		$this -> db -> select('SQL_CALC_FOUND_ROWS ' . str_replace(' , ', ' ', implode(', ', $aColumns)), false);
		$this -> db -> select("dc.id as id,drug, pack_size, u.name");
		$today = date('Y-m-d');
		$this -> db -> from("drugcode dc");
		$this -> db -> join("drug_unit u", "u.id=dc.unit");
		$this -> db -> where("dc.enabled", "1");
		$rResult = $this -> db -> get();
		// Data set length after filtering
		$this -> db -> select('FOUND_ROWS() AS found_rows');
		$iFilteredTotal = $this -> db -> get() -> row() -> found_rows;

		// Total data set length
		$this -> db -> select("dc.*");
		$this -> db -> from("drugcode dc");
		$this -> db -> join("drug_unit u", "u.id=dc.unit");
		$this -> db -> where("dc.enabled", "1");
		$tot_drugs = $this -> db -> get();
		$iTotal = count($tot_drugs -> result_array());

		// Output
		$output = array('sEcho' => intval($sEcho), 'iTotalRecords' => $iTotal, 'iTotalDisplayRecords' => $iFilteredTotal, 'aaData' => array());

		foreach ($rResult->result_array() as $aRow) {
			$sql = "select '" . $aRow['drug'] . "' as drug_name,'" . $aRow['pack_size'] . "' as pack_size,'" . $aRow['name'] . "' as unit, month(DATE(d_c.period)) as month,d_c.amount as total_consumed 
					from drug_cons_balance d_c 
					where d_c.drug_id='" . $aRow['id'] . "' and d_c.period LIKE '%" . $year . "%' and facility='" . $facility_code . "' order by d_c.period asc";
			$drug_details_sql = $this -> db -> query($sql);
			$sql_array = $drug_details_sql -> result_array();
			$drug_consumption = array();
			$count = count($sql_array);
			$drug_name = "";
			$unit = "";
			$pack_size = "";
			$y = 0;

			//if ($count > 0) {
			$row = array();
			foreach ($sql_array as $row) {
				$count++;
				$drug_name = $row['drug_name'];
				$unit = $row['unit'];
				$pack_size = $row['pack_size'];

				$month = $row['month'];
				//Replace the preceding 0 in months less than october
				if ($month < 10) {
					$month = str_replace('0', '', $row['month']);

				}
				
					
				$drug_consumption[$month] = $row['total_consumed'];
			}
			//Loop untill 12; check if there is a result for each month
			$row[] = $aRow['drug'];
			$row[] = $aRow['name'];
			$check_month = 0;
			for ($i = 1; $i <= 12; $i++) {

				if (isset($drug_consumption[$i]) and isset($pack_size) and $pack_size != 0) {
					if($pack_unit=='unit'){
						$row[] = $drug_consumption[$i];
					}elseif($pack_unit=='pack'){
						$row[] = ceil($drug_consumption[$i] / $pack_size);
					}			
				} else {
					$row[] = '-';
				}
			}
			$output['aaData'][] = $row;
		}
		echo json_encode($output);
	}

	public function stock_report($report_type, $stock_type = "", $start_date = "", $end_date = "") {
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['base_url'] = base_url();
		$data['stock_type'] = $stock_type;
		$data['title'] = "webADT | Reports";
		$data['selected_report_type'] = "Drug Inventory";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";

		if ($report_type == "drug_stock_on_hand") {
			$data['report_title'] = "Drug Stock On Hand";
			$data['content_view'] = 'reports/drugstock_on_hand_v';
		} else if ($report_type == "expiring_drug") {
			$data['report_title'] = "Expiring Drugs";
			$data['content_view'] = 'reports/expiring_drugs_v';
		} else if ($report_type == "drug_consumption") {
			//Get actual page
			if ($this -> uri -> segment(4) != "") {
				$data['year'] = $this -> uri -> segment(4);
			}
			$data['pack_unit'] = $start_date;//Generating packs or units is based on this parameter
			$data['report_title'] = "Drug consumption";
			$data['content_view'] = 'reports/drugconsumption_v';
		}
		//Facility commodity summary
		else if ($report_type == "commodity_summary") {
			if ($stock_type == 1) {
				$data['stock_type_n'] = 'Main Store';
			} else if ($stock_type == 2) {
				$data['stock_type_n'] = 'Pharmacy';
			}
			//Get transaction names
			$get_transaction_names = $this -> db -> query("SELECT id,name,effect FROM transaction_type 
															WHERE name LIKE '%received%' 
															OR name LIKE '%adjustment%' 
															OR name LIKE '%return%' OR name LIKE '%dispense%' 
															OR name LIKE '%issue%' OR name LIKE '%loss%' 
															OR name LIKE '%ajustment%' OR name LIKE '%physical%count%' 
															OR name LIKE '%starting%stock%' ");
			$get_transaction_array = $get_transaction_names -> result_array();
			$data['trans_names'] = $get_transaction_array;
			$data['start_date'] = date('d-M-Y', strtotime($this -> uri -> segment(5)));
			$data['end_date'] = date('d-M-Y', strtotime($this -> uri -> segment(6)));
			$data['report_title'] = "Facility Commodity Summary";
			$data['content_view'] = 'reports/commodity_summary_v';

		}

		$this -> load -> view('template', $data);
	}

	public function drug_stock_on_hand($stock_type) {
		$facility_code = $this -> session -> userdata('facility');

		//Store
		if ($stock_type == '1') {
			$stock_param = " AND (source='" . $facility_code . "' OR destination='" . $facility_code . "') AND source!=destination ";
		}
		//Pharmacy
		else if ($stock_type == '2') {
			$stock_param = " AND (source=destination) AND(source='" . $facility_code . "') ";
		}
		$data = array();
		/* Array of database columns which should be read and sent back to DataTables. Use a space where
		 * you want to insert a non-database field (for example a counter or static image)
		 */
		$aColumns = array('drug', 'pack_size');

		$iDisplayStart = $this -> input -> get_post('iDisplayStart', true);
		$iDisplayLength = $this -> input -> get_post('iDisplayLength', true);
		$iSortCol_0 = $this -> input -> get_post('iSortCol_0', false);
		$iSortingCols = $this -> input -> get_post('iSortingCols', true);
		$sSearch = $this -> input -> get_post('sSearch', true);
		$sEcho = $this -> input -> get_post('sEcho', true);

		// Paging
		if (isset($iDisplayStart) && $iDisplayLength != '-1') {
			$this -> db -> limit($this -> db -> escape_str($iDisplayLength), $this -> db -> escape_str($iDisplayStart));
		}

		// Ordering
		if (isset($iSortCol_0)) {
			for ($i = 0; $i < intval($iSortingCols); $i++) {
				$iSortCol = $this -> input -> get_post('iSortCol_' . $i, true);
				$bSortable = $this -> input -> get_post('bSortable_' . intval($iSortCol), true);
				$sSortDir = $this -> input -> get_post('sSortDir_' . $i, true);

				if ($bSortable == 'true') {
					$this -> db -> order_by($aColumns[intval($this -> db -> escape_str($iSortCol))], $this -> db -> escape_str($sSortDir));
				}
			}
		}

		/*
		 * Filtering
		 * NOTE this does not match the built-in DataTables filtering which does it
		 * word by word on any field. It's possible to do here, but concerned about efficiency
		 * on very large tables, and MySQL's regex functionality is very limited
		 */
		if (isset($sSearch) && !empty($sSearch)) {
			for ($i = 0; $i < count($aColumns); $i++) {
				$bSearchable = $this -> input -> get_post('bSearchable_' . $i, true);

				// Individual column filtering
				if (isset($bSearchable) && $bSearchable == 'true') {
					$this -> db -> or_like($aColumns[$i], $this -> db -> escape_like_str($sSearch));
				}
			}
		}

		// Select Data
		$this -> db -> select('SQL_CALC_FOUND_ROWS ' . str_replace(' , ', ' ', implode(', ', $aColumns)), false);
		$this -> db -> select("dc.id,u.Name,SUM(dsb.balance) as stock_level");
		$today = date('Y-m-d');
		$this -> db -> from("drugcode dc");
		$this -> db -> where('dc.enabled', '1');
		$this -> db -> where('dsb.facility_code', $facility_code);
		$this -> db -> where('dsb.expiry_date > ', $today);
		$this -> db -> where('dsb.stock_type ', $stock_type);
		$this -> db -> join("drug_stock_balance dsb", "dsb.drug_id=dc.id");
		$this -> db -> join("drug_unit u", "u.id=dc.unit", "left outer");
		$this -> db -> group_by("dsb.drug_id");

		$rResult = $this -> db -> get();

		// Data set length after filtering
		$this -> db -> select('FOUND_ROWS() AS found_rows');
		$iFilteredTotal = $this -> db -> get() -> row() -> found_rows;

		// Total data set length
		$this -> db -> select("dsb.*");
		$where = "dc.enabled='1' AND dsb.facility='$facility_code' AND dsb.expiry_date > CURDATE() AND dsb.stock_type='$stock_type'";
		$this -> db -> from("drugcode dc");
		$this -> db -> where('dc.enabled', '1');
		$this -> db -> where('dsb.facility_code', $facility_code);
		$this -> db -> where('dsb.expiry_date > ', $today);
		$this -> db -> where('dsb.stock_type ', $stock_type);
		$this -> db -> join("drug_stock_balance dsb", "dsb.drug_id=dc.id");
		$this -> db -> join("drug_unit u", "u.id=dc.unit");
		$this -> db -> group_by("dsb.drug_id");
		$tot_drugs = $this -> db -> get();
		$iTotal = count($tot_drugs -> result_array());

		// Output
		$output = array('sEcho' => intval($sEcho), 'iTotalRecords' => $iTotal, 'iTotalDisplayRecords' => $iFilteredTotal, 'aaData' => array());

		foreach ($rResult->result_array() as $aRow) {

			//Get consumption for the past three months
			$drug = $aRow['id'];
			$stock_level = $aRow['stock_level'];
			$safetystock_query = "SELECT SUM(d.quantity_out) AS TOTAL FROM drug_stock_movement d WHERE d.drug ='$drug' AND DATEDIFF(CURDATE(),d.transaction_date)<= 90 and facility='$facility_code' $stock_param";
			$safetystocks = $this -> db -> query($safetystock_query);
			$safetystocks_results = $safetystocks -> result_array();
			$three_monthly_consumption = 0;
			$stock_status = "";
			foreach ($safetystocks_results as $safetystocks_result) {
				$three_monthly_consumption = $safetystocks_result['TOTAL'];
				//Calculating Monthly Consumption hence Max-Min Inventory
				$monthly_consumption = ($three_monthly_consumption) / 3;
				$monthly_consumption = number_format($monthly_consumption, 2);

				//Therefore Maximum Consumption
				$maximum_consumption = $monthly_consumption * 3;
				$maximum_consumption = number_format($maximum_consumption, 2);

				//Therefore Minimum Consumption
				$minimum_consumption = $monthly_consumption * 1.5;
				//$minimum_consumption = number_format($monthly_consumption, 2);

				//If current stock balance is less than minimum consumption
				if ($stock_level < $minimum_consumption) {
					$stock_status = "<span class='red'>LOW</span>";
					if ($minimum_consumption < 0) {
						$minimum_consumption = 0;
					}
				}
			}

			$row = array();
			$x = 0;

			foreach ($aColumns as $col) {
				$x++;
				$row[] = strtoupper($aRow[$col]);
				if ($x == 1) {
					$row[] = $aRow['Name'];
				} else if ($x == 2) {

					//SOH IN Units
					//$row[]='<b style="color:green">'.number_format($aRow['stock_level']).'</b>';
					$row[] = number_format($aRow['stock_level']);
					//SOH IN Packs
					if (is_numeric($aRow['pack_size']) and $aRow['pack_size'] > 0) {
						$row[] = number_format(ceil($aRow['stock_level'] / $aRow['pack_size']));
					} else {
						$row[] = " - ";
					}

					//Safety Stock
					$row[] = number_format(ceil($minimum_consumption));
					$row[] = $stock_status;

				}

			}
			$output['aaData'][] = $row;
		}
		echo json_encode($output);
	}

	public function expiring_drugs($stock_type) {
		if ($stock_type == 1) {
			$data['stock_type'] = 'Main Store';
		} else if ($stock_type == 2) {
			$data['stock_type'] = 'Pharmacy';
		}
		$count = 0;
		$facility_code = $this -> session -> userdata('facility');
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$drugs_sql = "SELECT d.drug as drug_name,d.pack_size,u.name as drug_unit,dsb.batch_number as batch,dsb.balance as stocks_display,dsb.expiry_date,DATEDIFF(dsb.expiry_date,CURDATE()) as expired_days_display FROM drugcode d LEFT JOIN drug_unit u ON d.unit=u.id LEFT JOIN drug_stock_balance dsb ON d.id=dsb.drug_id WHERE DATEDIFF(dsb.expiry_date,CURDATE()) <=180 AND DATEDIFF(dsb.expiry_date,CURDATE())>=0 AND d.enabled=1 AND dsb.facility_code ='" . $facility_code . "' AND dsb.stock_type='" . $stock_type . "' AND dsb.balance>0 ORDER BY expired_days_display asc";
		$drugs = $this -> db -> query($drugs_sql);
		$results = $drugs -> result_array();

		$d = 0;
		$drugs = $results;
		$data['drug_details'] = $drugs;
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type'] = "Drug Inventory";
		$data['report_title'] = "Expiring Drugs within 6 months";
		$data['title'] = "Reports";
		$data['content_view'] = 'reports/expiring_drugs_v';
		$this -> load -> view('template', $data);

	}

	public function expired_drugs($stock_type) {
		$count = 0;
		$facility_code = $this -> session -> userdata('facility');
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		//$drugs_sql = "SELECT s.id AS id,s.drug AS Drug_Id,d.drug AS Drug_Name,d.pack_size AS pack_size, u.name AS Unit, s.batch_number AS Batch,s.expiry_date AS Date_Expired,DATEDIFF(CURDATE(),DATE(s.expiry_date)) AS Days_Since_Expiry FROM drugcode d LEFT JOIN drug_unit u ON d.unit = u.id LEFT JOIN drug_stock_movement s ON d.id = s.drug LEFT JOIN transaction_type t ON t.id=s.transaction_type WHERE t.effect=1 AND DATEDIFF(CURDATE(),DATE(s.expiry_date)) >0  AND d.enabled=1 AND s.facility ='" . $facility_code . "' GROUP BY Batch ORDER BY Days_Since_Expiry asc";
		$drugs_sql = "SELECT d.drug as drug_name,d.pack_size,u.name as drug_unit,dsb.batch_number as batch,dsb.balance as stocks_display,dsb.expiry_date,DATEDIFF(CURDATE(),dsb.expiry_date) as expired_days_display FROM drugcode d LEFT JOIN drug_unit u ON d.unit=u.id LEFT JOIN drug_stock_balance dsb ON d.id=dsb.drug_id WHERE DATEDIFF(CURDATE(),DATE(dsb.expiry_date)) >0  AND d.enabled=1 AND d.enabled=1 AND dsb.facility_code ='" . $facility_code . "' AND dsb.stock_type='" . $stock_type . "' AND dsb.balance>0 ORDER BY expired_days_display asc";
		$drugs = $this -> db -> query($drugs_sql);
		$results = $drugs -> result_array();
		//Get all expiring drugs
		//foreach ($results as $result => $value) {
		//	$count = 1;
		//$this -> getBatchInfo($value['Drug_Id'], $value['Batch'], $value['Unit'], $value['Drug_Name'], $value['Date_Expired'], $value['Days_Since_Expiry'], $value['id'], $value['pack_size'], $stock_type, $facility_code);
		//};

		$d = 0;
		$drugs = $results;
		$data['drug_details'] = $drugs;
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type'] = "Drug Inventory";
		$data['report_title'] = "Expired Drugs";
		$data['title'] = "Reports";
		$data['content_view'] = 'reports/expired_drugs_v';
		$this -> load -> view('template', $data);
	}

	public function getBatchInfo($drug, $batch, $drug_unit, $drug_name, $expiry_date, $expired_days, $drug_id, $pack_size, $stock_type, $facility_code) {
		$stock_status = 0;
		$stock_param = "";

		//Store
		if ($stock_type == '1') {
			$stock_param = " AND (source='" . $facility_code . "' OR destination='" . $facility_code . "') AND source!=destination ";
		}
		//Pharmacy
		else if ($stock_type == '2') {
			$stock_param = " AND (source=destination) AND(source='" . $facility_code . "') ";
		}
		$initial_stock_sql = "SELECT SUM( d.quantity ) AS Initial_stock, d.transaction_date AS transaction_date, '" . $batch . "' AS batch FROM drug_stock_movement d WHERE d.drug =  '" . $drug . "' AND facility='" . $facility_code . "' " . $stock_param . " AND transaction_type =  '11' AND d.batch_number =  '" . $batch . "'";
		$batches = $this -> db -> query($initial_stock_sql);
		$batch_results = $batches -> result_array();
		foreach ($batch_results as $batch_result => $value) {
			$initial_stock = $value['Initial_stock'];
			//Check if initial stock is present meaning physical count done
			if ($initial_stock != null) {
				$batch_stock_sql = "SELECT (SUM( ds.quantity ) - SUM( ds.quantity_out )) AS stock_levels, ds.batch_number,ds.expiry_date FROM drug_stock_movement ds WHERE ds.transaction_date BETWEEN  '" . $value['transaction_date'] . "' AND curdate() AND facility='" . $facility_code . "' " . $stock_param . " AND ds.drug ='" . $drug . "'  AND ds.batch_number ='" . $value['batch'] . "'";
				$second_row = $this -> db -> query($batch_stock_sql);
				$second_rows = $second_row -> result_array();

				foreach ($second_rows as $second_row => $value) {
					if ($value['stock_levels'] > 0) {
						$batch_balance = $value['stock_levels'];
						$batch_expiry = $expiry_date;
						$ed = substr($expired_days, 0, 1);
						if ($ed == "-") {
							$expired_days = $expired_days;
						}

						$batch_stock = $batch_balance / $pack_size;
						$expired_days_display = number_format($expired_days);
						$stocks_display = ceil(number_format($batch_stock, 1));

						$this -> drug_array[$this -> counter]['drug_name'] = $drug_name;
						$this -> drug_array[$this -> counter]['drug_unit'] = $drug_unit;
						$this -> drug_array[$this -> counter]['batch'] = $batch;
						$this -> drug_array[$this -> counter]['expiry_date'] = $batch_expiry;
						$this -> drug_array[$this -> counter]['stocks_display'] = $stocks_display;
						$this -> drug_array[$this -> counter]['expired_days_display'] = $expired_days_display;
						$this -> counter++;
					}
				}

			} else {

				$batch_stock_sql = "SELECT (SUM( ds.quantity ) - SUM( ds.quantity_out ) ) AS stock_levels, ds.batch_number,ds.expiry_date FROM drug_stock_movement ds WHERE ds.drug =  '" . $drug . "' AND facility='" . $facility_code . "' " . $stock_param . " AND ds.expiry_date > curdate() AND ds.batch_number='" . $value['batch'] . "'";
				$second_row = $this -> db -> query($batch_stock_sql);
				$second_rows = $second_row -> result_array();

				foreach ($second_rows as $second_row => $value) {

					if ($value['stock_levels'] > 0) {
						$batch_balance = $value['stock_levels'];
						$batch_expiry = $expiry_date;
						$ed = substr($expired_days, 0, 1);
						if ($ed == "-") {

							$expired_days = $expired_days;
						}
						//If pack size is zero or null
						if ($pack_size == "" or $pack_size == 0) {
							$batch_stock = $batch_balance;
						} else {
							$batch_stock = $batch_balance / $pack_size;
						}

						$expired_days_display = number_format($expired_days);

						$stocks_display = number_format($batch_stock, 1);

						$this -> drug_array[$this -> counter]['drug_name'] = $drug_name;
						$this -> drug_array[$this -> counter]['drug_unit'] = $drug_unit;
						$this -> drug_array[$this -> counter]['batch'] = $batch;
						$this -> drug_array[$this -> counter]['expiry_date'] = $batch_expiry;
						$this -> drug_array[$this -> counter]['stocks_display'] = $stocks_display;
						$this -> drug_array[$this -> counter]['expired_days_display'] = $expired_days_display;
						$this -> counter++;
					}
				}
			}

		}
	}

	public function commodity_summary($stock_type = "1", $start_date = "", $end_date = "") {

		//$start_date = date('Y-m-d', strtotime($start_date));
		//$end_date = date('Y-m-d', strtotime($end_date));
		$facility_code = $this -> session -> userdata('facility');
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		//Get All Drugs,Unit Size and Pack size
		$sql = "SELECT '$facility_code' as facility,d.id as id,drug, pack_size, name from drugcode d left join drug_unit u on d.unit = u.id where d.Enabled=1";
		$get_facility_sql = $this -> db -> query($sql);
		$get_commodity_array = $get_facility_sql -> result_array();

		//Get transaction names
		$sql = "SELECT id,name,effect FROM transaction_type WHERE name LIKE '%received%' OR name LIKE '%adjustment%' OR name LIKE '%return%' OR name LIKE '%dispense%' OR name LIKE '%issue%' OR name LIKE '%loss%' OR name LIKE '%ajustment%' OR name LIKE '%physical%count%' OR name LIKE '%starting%stock%'";
		$get_transaction_names = $this -> db -> query($sql);
		$get_transaction_array = $get_transaction_names -> result_array();

		//Search for physical count/starting stock id
		$phys_count_id = $this -> searchForTransactionId("starting", $get_transaction_array);
		//Starting Stock not found,try physical count
		if ($phys_count_id == "") {
			$phys_count_id = $this -> searchForTransactionId("physical", $get_transaction_array);
		}

		/* Server side start */
		$data = array();
		$aColumns = array('drug');
		$iDisplayStart = $this -> input -> get_post('iDisplayStart', true);
		$iDisplayLength = $this -> input -> get_post('iDisplayLength', true);
		$iSortCol_0 = $this -> input -> get_post('iSortCol_0', false);
		$iSortingCols = $this -> input -> get_post('iSortingCols', true);
		$sSearch = $this -> input -> get_post('sSearch', true);
		$sEcho = $this -> input -> get_post('sEcho', true);

		// Paging
		if (isset($iDisplayStart) && $iDisplayLength != '-1') {
			$this -> db -> limit($this -> db -> escape_str($iDisplayLength), $this -> db -> escape_str($iDisplayStart));
		}

		// Ordering
		if (isset($iSortCol_0)) {
			for ($i = 0; $i < intval($iSortingCols); $i++) {
				$iSortCol = $this -> input -> get_post('iSortCol_' . $i, true);
				$bSortable = $this -> input -> get_post('bSortable_' . intval($iSortCol), true);
				$sSortDir = $this -> input -> get_post('sSortDir_' . $i, true);

				if ($bSortable == 'true') {
					$this -> db -> order_by($aColumns[intval($this -> db -> escape_str($iSortCol))], $this -> db -> escape_str($sSortDir));
				}
			}
		}
		/*
		 * Filtering
		 * NOTE this does not match the built-in DataTables filtering which does it
		 * word by word on any field. It's possible to do here, but concerned about efficiency
		 * on very large tables, and MySQL's regex functionality is very limited
		 */
		if (isset($sSearch) && !empty($sSearch)) {
			for ($i = 0; $i < count($aColumns); $i++) {
				$bSearchable = $this -> input -> get_post('bSearchable_' . $i, true);
				// Individual column filtering
				if (isset($bSearchable) && $bSearchable == 'true') {
					$this -> db -> or_like($aColumns[$i], $this -> db -> escape_like_str($sSearch));
				}
			}
		}

		// Select Data
		//$get_facility_sql = $this -> db -> query("SELECT '$facility_code' as facility,d.id as id,drug, pack_size, name from drugcode d left join drug_unit u on d.unit = u.id where d.Enabled=1");
		$this -> db -> select('SQL_CALC_FOUND_ROWS ' . str_replace(' , ', ' ', implode(', ', $aColumns)), false);
		$this -> db -> select("'$facility_code' as facility,dc.id,dc.pack_size,u.name");
		$today = date('Y-m-d');
		$this -> db -> from("drugcode dc");
		$this -> db -> join("drug_unit u", "u.id=dc.unit", 'left outer');
		$this -> db -> where("dc.Enabled", 1);
		$rResult = $this -> db -> get();

		// Data set length after filtering
		$this -> db -> select('FOUND_ROWS() AS found_rows');
		$iFilteredTotal = $this -> db -> get() -> row() -> found_rows;

		// Total data set length
		$this -> db -> select("dc.*");
		$this -> db -> from("drugcode dc");
		$this -> db -> join("drug_unit u", "u.id=dc.unit", 'left outer');
		$this -> db -> where("dc.Enabled", 1);
		$tot_drugs = $this -> db -> get();
		$iTotal = count($tot_drugs -> result_array());

		// Output
		$output = array('sEcho' => intval($sEcho), 'iTotalRecords' => $iTotal, 'iTotalDisplayRecords' => $iFilteredTotal, 'aaData' => array());

		foreach ($rResult->result_array() as $aRow) {
			$row = array();
			$this -> getDrugInfo($facility_code, $aRow['id'], $aRow['drug'], $aRow['name'], $aRow['pack_size'], $start_date, $end_date, $stock_type, $get_transaction_array, $phys_count_id, $output, $row);
			if ($this -> com_summary != '') {
				$output['aaData'][] = $this -> com_summary;
			}
			$this -> com_summary = "";

		}
		$output['aaData'] = array_filter($output['aaData']);
		$output['aaData'] = array_values($output['aaData']);
		
		echo json_encode($output);
		/*

		 foreach ($get_commodity_array as $parent_row) {

		 $this -> getDrugInfo($facility_code, $parent_row['id'], $parent_row['drug'], $parent_row['name'], $parent_row['pack_size'], $start_date, $end_date, $stock_type,$get_transaction_array,$phys_count_id,$output);
		 >>>>>>> be866de339ea296f8d57447de98b16351e4f137b
		 }

		 foreach ($get_commodity_array as $parent_row) {
		 $this -> getDrugInfo($facility_code, $parent_row['id'], $parent_row['drug'], $parent_row['name'], $parent_row['pack_size'], $start_date, $end_date, $stock_type, $get_transaction_array, $phys_count_id);
		 }
		 $data['stock_type_n'] = "";
		 if ($stock_type == 1) {
		 $data['stock_type_n'] = "Main Store";
		 } else if ($stock_type == 2) {
		 $data['stock_type_n'] = "Pharmacy";
		 }
		 //echo var_dump($this -> commodity_details) ;die();
		 $data['start_date'] = date('d-M-Y', strtotime($start_date));
		 $data['end_date'] = date('d-M-Y', strtotime($end_date));
		 $data['drug_details'] = $this -> commodity_details;
		 $data['trans_names'] = $get_transaction_array;
		 $data['title'] = "webADT | Reports";
		 $data['hide_side_menu'] = 1;
		 $data['banner_text'] = "Facility Reports";
		 $data['selected_report_type'] = "Drug Inventory";
		 $data['report_title'] = "Facility Commodity Summary";
		 $data['content_view'] = 'reports/commodity_summary_v';
		 $this -> load -> view('template', $data);
		 */

	}

	function searchForTransactionId($name, $array) {
		foreach ($array as $key => $val) {
			$s_name = strtolower($val['name']);
			if (strpos($s_name, $name) === 0) {
				return $val['id'];
			}
		}
		return null;
	}

	public function getDrugInfo($facility_code, $drug, $drug_name, $drug_unit, $drug_packsize, $start_date, $end_date, $stock_type, $transaction_names = "", $phys_count_id = 0, $output = "", $row = "") {
		$stock_param = "";
		//Store
		$stock_param = " AND ccc_store_sp = $stock_type";
		
		$stock_status = 0;
		$counter = 0;
		$k = 0;
		$batch_status_row_string = "";
		//Query to get all batches that have not expired
		$all_batches = "SELECT DISTINCT d.batch_number AS batch FROM drug_stock_balance d WHERE d.drug_id =  '" . $drug . "' AND expiry_date > '" . $start_date . "' AND facility_code='" . $facility_code . "' AND stock_type='" . $stock_type . "' GROUP BY d.batch_number";
		$get_batches_sql = $this -> db -> query($all_batches);
		$get_batches_array = $get_batches_sql -> result_array();
		$get_batches_count = count($get_batches_array);
		foreach ($get_batches_array as $batch_row) {
			if ($get_batches_count == 0) {
				$this -> getSafetyStock($drug, $stock_status, $drug_name, $drug_unit, $drug_packsize, $facility_code, $stock_type, $start_date, $end_date, $transaction_names, $phys_count_id, $output, $row);
			}
			$batch_no = $batch_row['batch'];
			//Check if there is a physical count
			$initial_stock = "SELECT SUM( d.quantity ) AS Initial_stock, d.transaction_date AS transaction_date, '" . $batch_no . "' AS batch,'" . $k . "' AS counter FROM drug_stock_movement d WHERE d.drug =  '" . $drug . "' AND facility='" . $facility_code . "' " . $stock_param . " AND transaction_type =  '$phys_count_id' AND d.batch_number =  '" . $batch_no . "'";
			$initial_stock_sql = $this -> db -> query($initial_stock);
			$initial_stock_array = $initial_stock_sql -> result_array();
			foreach ($initial_stock_array as $physical_row) {
				$init_stock = $physical_row['Initial_stock'];
				//Check if initial stock is present meaning physical count done
				if ($init_stock != null) {
					$batch_stock = "SELECT (SUM( ds.quantity ) - SUM( ds.quantity_out )) AS stock_levels, '" . $physical_row['counter'] . "' AS counter, ds.batch_number FROM drug_stock_movement ds WHERE ds.transaction_date BETWEEN  '" . $physical_row['transaction_date'] . "' AND '" . $end_date . "' AND facility='" . $facility_code . "' AND ds.drug ='" . $drug . "' " . $stock_param . "  AND ds.batch_number ='" . $physical_row['batch'] . "'";
					$batch_stock_sql = $this -> db -> query($batch_stock);
					$batch_stock_array = $batch_stock_sql -> result_array();
					foreach ($batch_stock_array as $second_row) {
						if ($second_row['stock_levels'] > 0) {
							$stock_status += $second_row['stock_levels'];
						}
						if ($second_row['counter'] == ($get_batches_count - 1)) {
							$this -> getSafetyStock($drug, $stock_status, $drug_name, $drug_unit, $drug_packsize, $facility_code, $stock_type, $start_date, $end_date, $transaction_names, $phys_count_id, $output, $row);
						}
					}
				} else {

					$batch_stock = "SELECT (SUM( ds.quantity ) - SUM( ds.quantity_out ) ) AS stock_levels, '" . $physical_row['counter'] . "' AS counter, ds.batch_number FROM drug_stock_movement ds WHERE ds.drug =  '" . $drug . "' AND ds.expiry_date > '" . $start_date . "'AND facility='" . $facility_code . "' " . $stock_param . " AND date(ds.transaction_date) <= date('" . $start_date . "') AND ds.batch_number='" . $physical_row['batch'] . "'";
					//$batch_stock="SELECT ds.balance as stock_levels, '" . $physical_row['counter'] . "' AS counter,ds.batch_number drug_stock_movement ds WHERE ds.drug =  '" . $drug . "' AND ds.expiry_date > '" . $start_date . "'AND facility='" . $facility_code . "' " . $stock_param . " AND date(ds.transaction_date) <= date('" . $start_date . "') AND ds.batch_number='" . $physical_row['batch'] . "'";
					//echo $batch_stock; die();

					$batch_stock_sql = $this -> db -> query($batch_stock);
					$batch_stock_array = $batch_stock_sql -> result_array();
					foreach ($batch_stock_array as $second_row) {

						if ($second_row['stock_levels'] > 0) {
							$stock_status += $second_row['stock_levels'];
						}
						if ($second_row['counter'] == ($get_batches_count - 1)) {
							$this -> getSafetyStock($drug, $stock_status, $drug_name, $drug_unit, $drug_packsize, $facility_code, $stock_type, $start_date, $end_date, $transaction_names, $phys_count_id, $output, $row);
						}
					}

				}
			}
			$k++;
		}

	}

	public function getSafetyStock($drug, $stock_status, $drug_name, $drug_unit, $drug_packsize, $facility_code, $stock_type, $start_date, $end_date, $transaction_names = "", $phys_count_id = 0, $output = "", $row = "") {
		$stock_param = "";
		//Store
		$stock_param = " AND ccc_store_sp = $stock_type";
		$trans_counter = 0;
		$trans_count = count($transaction_names);
		//echo var_dump($transaction_names);die();
		$counter = 0;
		$row_string = "";
		while ($trans_counter <= ($trans_count - 1)) {
			$trans_name = $transaction_names[$trans_counter]['name'];
			$trans_name_lower = strtolower($transaction_names[$trans_counter]['name']);
			$transaction_effect = $transaction_names[$trans_counter]['effect'];

			if (strpos($trans_name_lower, "received") === 0 || (strpos($trans_name_lower, "returns") === 0 && $transaction_effect == 1) || (strpos($trans_name_lower, "ajustment") === 0 && $transaction_effect == 1) || strpos($trans_name_lower, "startingstock") === 0 || strpos($trans_name_lower, "physicalcount") === 0) {
				$choice = "dsm.quantity";
			} else {
				$choice = "dsm.quantity_out";
			}
			//echo $trans_name." - ".$transaction_names[$trans_counter]['id']." - ".$choice."<br>";
			$sql = "select '" . $drug . "' as drug_id ,'" . $drug_name . "' as drug_name ,dsm.transaction_type AS type , SUM(" . $choice . ") AS TOTAL FROM transaction_type tt, drug_stock_movement dsm WHERE DATE( dsm.transaction_date ) BETWEEN DATE('" . $start_date . "' ) AND DATE( '" . $end_date . "' ) AND dsm.transaction_type ='" . $transaction_names[$trans_counter]['id'] . "' AND dsm.drug ='" . $drug . "' AND tt.id = dsm.transaction_type AND dsm.facility='" . $facility_code . "' " . $stock_param;
			$safety_stock_sql = $this -> db -> query($sql);
			$safety_stock_array = $safety_stock_sql -> result_array();
			foreach ($safety_stock_array as $first_row) {
				$default_total = $first_row['TOTAL'];
				if ($default_total == null) {
					$default_total = 0;
				}
				$default_total_display = number_format($default_total, 1);
				$row_string .= "<td align='center'>" . $default_total_display . "</td>";
				//Put details for received drugs, return from patients,... in an array
				//$this -> commodity_details[$this -> count_rows][$trans_name] = $default_total_display;

				if ($counter == 0) {
					//Add drug name
					$row[0] = $first_row['drug_name'];
					//Add begining balance
					$row[1] = number_format($stock_status);
				}
				$row[] = $default_total_display;
				$counter++;
				if ($counter == 9) {
					//After loopin through other columns, add Drug Name and stock status
					//$this -> commodity_details[$this -> count_rows]["drug_name"] = $first_row['drug_name'];
					//$this -> commodity_details[$this -> count_rows]["stock_status"] = number_format($stock_status);

					$counter = 0;
					$this -> count_rows++;
					$this -> com_summary = $row;

				}
			}
			$trans_counter++;
		}
	}

	public function patients_who_changed_regimen($start_date = "", $end_date = "") {
		$data['from'] = $start_date;
		$data['to'] = $end_date;
		$facility_code = $this -> session -> userdata('facility');
		$start_date = date('Y-m-d', strtotime($start_date));
		$end_date = date('Y-m-d', strtotime($end_date));
		/*
		 * Get All active patients
		 * Get Transactions of patients who visited in the selected period and changed regimens
		 */
		$sql = "SELECT CONCAT_WS(  ' | ', r2.regimen_code, r2.regimen_desc ) AS from_regimen, CONCAT_WS(  ' | ', r1.regimen_code, r1.regimen_desc ) AS to_regimen, p.patient_number_ccc AS art_no, CONCAT_WS(  ' ', CONCAT_WS(  ' ', p.first_name, p.other_name ) , p.last_name ) AS full_name, pv.dispensing_date, rst.name AS service_type,IF(rcp.name is not null,rcp.name,pv.regimen_change_reason) as regimen_change_reason 
				FROM patient p 
				LEFT JOIN regimen_service_type rst ON rst.id = p.service 
				LEFT JOIN patient_status ps ON ps.id = p.current_status 
				LEFT JOIN (
							SELECT * FROM patient_visit 
							WHERE dispensing_date BETWEEN  '$start_date' AND  '$end_date' AND last_regimen != regimen AND last_regimen IS NOT NULL
							ORDER BY id DESC
							) AS pv ON pv.patient_id = p.patient_number_ccc 
				LEFT JOIN regimen r1 ON r1.id = pv.regimen 
				LEFT JOIN regimen r2 ON r2.id = pv.last_regimen 
				LEFT JOIN regimen_change_purpose rcp ON rcp.id=pv.regimen_change_reason 
				WHERE ps.Name LIKE  '%active%' 
				AND r2.regimen_code IS NOT NULL 
				AND r1.regimen_code IS NOT NULL 
				AND pv.dispensing_date IS NOT NULL 
				AND r2.regimen_code NOT LIKE '%oi%' 
				GROUP BY pv.patient_id, pv.dispensing_date";
		$patient_sql = $this -> db -> query($sql);
		$data['patients'] = $patient_sql -> result_array();
		$data['total'] = count($data['patients']);
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "early_warning_report_select";
		$data['selected_report_type'] = "Early Warning Indicators";
		$data['report_title'] = "Active Patients who Have Changed Regimens";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/patients_who_changed_regimen_v';
		$this -> load -> view('template', $data);
	}

	public function patients_starting($start_date = "", $end_date = "") {
		$data['from'] = $start_date;
		$data['to'] = $end_date;
		$start_date = date('Y-m-d', strtotime($start_date));
		$end_date = date('Y-m-d', strtotime($end_date));
		$facility_code = $this -> session -> userdata('facility');
		$patient_sql = $this -> db -> query("SELECT distinct r.regimen_desc AS Regimen,UPPER(p.first_name)As First,UPPER(p.last_name) AS Last,p.patient_number_ccc AS Patient_Id FROM patient p LEFT JOIN regimen r ON r.id = p.start_regimen WHERE DATE(p.start_regimen_date) between DATE('" . $start_date . "') and DATE('" . $end_date . "') and p.facility_code='" . $facility_code . "' ORDER BY Patient_Id DESC");
		$data['patients'] = $patient_sql -> result_array();
		$data['total'] = count($data['patients']);
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "early_warning_report_select";
		$data['selected_report_type'] = "Early Warning Indicators";
		$data['report_title'] = "List of Patients Starting (By Regimen)";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/patients_starting_v';
		$this -> load -> view('template', $data);
	}

	public function early_warning_indicators($start_date = "", $end_date = "") {
		$data['from'] = $start_date;
		$data['to'] = $end_date;
		$facility_code = $this -> session -> userdata('facility');
		$start_date = date('Y-m-d', strtotime($start_date));
		$end_date = date('Y-m-d', strtotime($end_date));
		//Get Total Patients started on ART
		$sql = "SELECT COUNT( * ) AS Total_Patients "
                        . " FROM patient p "
                        . " LEFT JOIN regimen_service_type rst ON rst.id=p.service "
                        . " LEFT JOIN regimen r ON r.id=p.start_regimen "
                        . " LEFT JOIN patient_source ps ON ps.id = p.source"
                        . " WHERE p.start_regimen_date"
                        . " BETWEEN '" . $start_date . "'"
                        . " AND '" . $end_date . "'"
                        . " AND p.facility_code='" . $facility_code . "'"
                        . " AND rst.name LIKE '%art%' "
                        . " AND ps.name NOT LIKE '%transfer%'"
                        . " AND p.start_regimen !=''";
		$tot_patients_sql = $this -> db -> query($sql);
		$tot_patients = 0;
		$patients = $tot_patients_sql -> result_array();
		foreach ($patients as $value) {
			$tot_patients = $value['Total_Patients'];
		}
		//Get Total Patients started on first line ART
		$sql = "SELECT COUNT( * ) AS First_Line "
                     . "FROM patient p "
                     . "LEFT JOIN regimen_service_type rst ON rst.id=p.service "
                     . "LEFT JOIN regimen r ON r.id = p.start_regimen "
                     . "LEFT JOIN patient_source ps ON ps.id = p.source "
                     . "WHERE p.start_regimen_date "
                     . "BETWEEN '" . $start_date . "' "
                     . "AND '" . $end_date . "' "
                     . "AND r.line=1 "
                     . "AND p.facility_code='" . $facility_code . "' "
                     . "AND rst.name LIKE '%art%' "
                     . "AND ps.name NOT LIKE '%transfer%'"
                     . "AND p.start_regimen !=''";
		$first_line_sql = $this -> db -> query($sql);
		$first_line = 0;
		$first_line_array = $first_line_sql -> result_array();
		foreach ($first_line_array as $value) {
			$first_line = $value['First_Line'];
		}
		$percentage_firstline = 0;
		$percentage_onotherline = 0;
		if ($tot_patients == 0) {
			$percentage_firstline = 0;
			$percentage_onotherline = 0;
		} else {
			$percentage_firstline = ($first_line / $tot_patients) * 100;
			$percentage_onotherline = 100 - $percentage_firstline;
		}

		//Gets patients started a year ago within selected period
		$to_date = date('Y-m-d', strtotime($start_date . " -1 year"));
		$future_date = date('Y-m-d', strtotime($end_date . " -1 year"));
                
                $sql = "SELECT COUNT( * ) AS Total_Patients "
                        . " FROM patient p "
                        . " LEFT JOIN regimen_service_type rst ON rst.id=p.service "
                        . " LEFT JOIN regimen r ON r.id=p.start_regimen "
                        . " LEFT JOIN patient_source ps ON ps.id = p.source"
                        . " WHERE p.start_regimen_date"
                        . " BETWEEN '" . $to_date . "'"
                        . " AND '" . $future_date . "'"
                        . " AND p.facility_code='" . $facility_code . "'"
                        . " AND rst.name LIKE  '%art%' "
                        . " AND ps.name NOT LIKE '%transfer%'"
                        . " AND p.start_regimen !=''";
		$patient_from_period_sql = $this -> db -> query($sql);
		$total_from_period_array = $patient_from_period_sql -> result_array();
		$total_from_period = 0;
		foreach ($total_from_period_array as $value) {
			$total_from_period = $value['Total_Patients'];
		}

		//Gets patients started a year ago within selected period still in first line
		$stil_in_first_line = 0;
		$sql = "SELECT COUNT( * ) AS Total_Patients "
                        . "FROM patient p "
                        . "LEFT JOIN regimen_service_type rst ON rst.id=p.service "
                        . "LEFT JOIN regimen r ON r.id=p.start_regimen "
                        . "LEFT JOIN regimen r1 ON r1.id = p.current_regimen "
                        . "LEFT JOIN patient_source ps ON ps.id = p.source "
                        . "LEFT JOIN patient_status pt ON pt.id = p.current_status "
                        . "WHERE p.start_regimen_date "
                        . "BETWEEN '" . $to_date . "' "
                        . "AND '" . $future_date . "' "
                        . "AND p.facility_code='" . $facility_code . "' "
                        . "AND rst.name LIKE '%art%' "
                        . "AND ps.name NOT LIKE '%transfer%' "
                        . "AND r.line=1 "
                        . "AND r1.line ='1' "
                        . "AND pt.Name LIKE '%active%'";
                $first_line_patient_from_period_sql = $this -> db -> query($sql);
		$first_line_patient_from_period_array = $first_line_patient_from_period_sql -> result_array();
		foreach ($first_line_patient_from_period_array as $row) {
			$stil_in_first_line = $row['Total_Patients'];
		}
		if ($total_from_period == 0 || $stil_in_first_line == 0) {
			$percentage_stillfirstline = 0;

		} else {
			$percentage_stillfirstline = ($stil_in_first_line / $total_from_period) * 100;
		}

		//Gets patients started a year ago within selected period
        $total_before_period=$total_from_period;
		//Gets patients started a year ago within selected period lost to follow-up
		$sql = "SELECT COUNT( * ) AS Total_Patients "
                        . "FROM patient p "
                        . "LEFT JOIN regimen_service_type rst ON rst.id=p.service "
                        . "LEFT JOIN regimen r ON r.id=p.start_regimen "
                        . "LEFT JOIN patient_source ps ON ps.id = p.source "
                        . "LEFT JOIN patient_status pt ON pt.id = p.current_status "
                        . "WHERE p.start_regimen_date "
                        . "BETWEEN '" . $to_date . "' "
                        . "AND '" . $future_date . "' "
                        . "AND p.facility_code='" . $facility_code . "' "
                        . "AND rst.name LIKE '%art%' "
                        . "AND ps.name NOT LIKE '%transfer%' "
                        . "AND pt.Name LIKE '%lost%'";
                $patient_lost_followup_sql = $this -> db -> query($sql);
		$patient_lost_followup_array = $patient_lost_followup_sql -> result_array();
		$lost_to_follow = 0;
		foreach ($patient_lost_followup_array as $row2) {
			$lost_to_follow = $row2['Total_Patients'];
		}
		if ($lost_to_follow == 0 || $total_before_period == 0) {
			$percentage_lost_to_follow = 0;
		} else {
			$percentage_lost_to_follow = ($lost_to_follow / $total_before_period) * 100;
		}
		$data['tot_patients'] = $tot_patients;
		$data['first_line'] = $first_line;
		$data['percentage_firstline'] = $percentage_firstline;
		$data['percentage_onotherline'] = $percentage_onotherline;
		$data['total_patients'] = $tot_patients;
		$data['total_from_period'] = $total_from_period;
		$data['stil_in_first_line'] = $stil_in_first_line;
		$data['percentage_stillfirstline'] = number_format($percentage_stillfirstline, 1);
		$data['total_before_period'] = $total_before_period;
		$data['lost_to_follow'] = $lost_to_follow;
		$data['percentage_lost_to_follow'] = number_format($percentage_lost_to_follow, 1);
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "early_warning_report_select";
		$data['selected_report_type'] = "Early Warning Indicators";
		$data['report_title'] = "HIV Early Warning Indicators";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/early_warning_indicators_v';
		$this -> load -> view('template', $data);
	}

	public function graph_patients_enrolled_in_year($year = "") {
		$main_array=array();
		$facility_code = $this -> session -> userdata('facility');
		$months=array(
			      '1'=>'Jan',
			      '2'=>'Feb',
			      '3'=>'Mar',
			      '4'=>'Apr',
			      '5'=>'May',
			      '6'=>'Jun',
			      '7'=>'Jul',
			      '8'=>'Aug',
			      '9'=>'Sep',
			      '10'=>'Oct',
			      '11'=>'Nov',
			      '12'=>'Dec');

		$services_data=Regimen_Service_Type::getHydratedAll();
		foreach($services_data as $service){
			$services[]=$service['Name'];
		}

		//Loop through all services
		foreach($services as $service){
			$service_array=array();
			$month_data=array();
			$service_array['name']=$service;
			//Loop through all months
			foreach($months as $month=>$month_name){
				$sql = "SELECT COUNT(*) AS total
					    FROM patient p 
					    LEFT JOIN regimen_service_type rst ON p.service=rst.id
					    WHERE YEAR(p.date_enrolled)='$year' 
					    AND MONTH(p.date_enrolled)='$month'
					    AND rst.name LIKE '%$service%'
					    AND p.facility_code='$facility_code'";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				if($results){
                    $month_data[]=@(int)$results[0]['total'];
				}else{
					$month_data[]=0;
				}
		    }
		    $service_array['data']=$month_data;
		    //append service data to main array
		    $main_array[]=$service_array;
		}
        //chart data
		$resultArray = json_encode($main_array);
		$categories = json_encode(array_values($months));
		//chart settings
		$data['resultArraySize'] =7;
		$data['container'] = 'chart_sales';
		$data['chartType'] = 'line';
		$data['title'] = 'Chart';
		$data['chartTitle'] = 'Listing of Patients Enrolled for the Year: '.$year;
		$data['categories'] = $categories;
		$data['xAxix'] = 'Months of the Year';
		$data['suffix']= '';
		$data['yAxix'] = 'Totals';
		$data['resultArray'] = $resultArray;
		$data['graphs'] = $this -> load -> view('graph_v', $data,TRUE);
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "standard_report_row";
		$data['selected_report_type'] = "Standard Reports";
		$data['report_title'] = "Graph of Number of Patients Enrolled Per Month in a Given Year";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/graphs_on_patients_v';
		$this -> load -> view('template', $data);
	}

	public function patients_adherence($start_date = "", $end_date = "") {
		$data['from'] = $start_date;
		$data['to'] = $end_date;
		$start_date = date('Y-m-d', strtotime($start_date));
		$end_date = date('Y-m-d', strtotime($end_date));
		$facility_code = $this -> session -> userdata('facility');
		$sql = "SELECT SUM( months_of_stock ) - SUM(pill_count) AS pill_count, SUM( pill_count ) AS e_pill_count,SUM( months_of_stock ) AS a_pill_count,SUM(missed_pills) as missed_pills,adherence,SUM(pv.quantity) as quantity, frequency, p.patient_number_ccc, p.service, p.gender,(YEAR(curdate()) - YEAR(p.dob)) as age 
				FROM patient_visit pv 
				LEFT JOIN patient p ON p.patient_number_ccc = pv.patient_id 
				LEFT JOIN dose ds ON ds.name = pv.dose 
				LEFT JOIN drugcode dc ON dc.id = pv.drug_id 
				LEFT JOIN regimen r ON pv.regimen = r.id
				LEFT JOIN drug_classification cl ON cl.id = dc.classification 
				WHERE dispensing_date BETWEEN  '$start_date' AND  '$end_date' 
				AND pv.facility ='$facility_code' AND frequency <=2 
				AND (r.regimen_code NOT LIKE '%OI%' OR dc.drug LIKE '%COTRIMOXAZOLE%' OR dc.drug LIKE '%DAPSONE%' ) 
				AND (cl.Name LIKE '%art%' OR cl.Name LIKE '%anti%tb%') 
				GROUP BY p.patient_number_ccc";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		$data['results'] = $results;
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "early_warning_report_select";
		$data['selected_report_type'] = "Early Warning Indicators";
		$data['report_title'] = "Patient Adherence";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/patient_adherence_v';
		$this -> load -> view('template', $data);
	}

	public function graphical_adherence($type="appointment",$start_date = "", $end_date = ""){
		$data['start_date'] = date('Y-m-d',strtotime($start_date));
		$data['end_date'] = date('Y-m-d',strtotime($end_date));
		$data['type'] = $type;
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "early_warning_report_select";
		$data['selected_report_type'] = "Early Warning Indicators";
			
		if($type=="appointment"){
			$data['report_title'] = "Graphical Patient Adherence By Appointment";
		}
		else if($type=="pill_count"){
			$data['report_title'] = "Graphical Patient Adherence By Pill Count";
		} 
		
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/graphical_adherence_v';
		$this -> load -> view('template', $data);
	}

	public function getAdherence($type="appointment",$start_date = "", $end_date = "" ,$type = "")
	{	
		$ontime = 0;
		$missed = 0;
		$defaulter = 0;
		$lost_to_followup = 0;
		$overview_total = 0;

		$art_total = 0;
		$non_art_total = 0;
		$male_total = 0;
		$female_total = 0;
		$fifteen_total = 0;
		$over_fifteen_total = 0;
		$twenty_four_total = 0;

		$adherence = array( 
						'total' => 0,
			            'on_time' => 0,
			            'missed' => 0,
			            'defaulter'=> 0,
			            'lost_to_followup'=> 0);

		$art_adherence['art']  = array( 
			                        'total' => 0,
						            'on_time' => 0,
						            'missed' => 0,
						            'defaulter'=> 0,
						            'lost_to_followup'=> 0);
		$art_adherence['non_art']  = array( 
				                        'total' => 0,
							            'on_time' => 0,
							            'missed' => 0,
							            'defaulter'=> 0,
							            'lost_to_followup'=> 0);

		$gender_adherence['male']  = array( 
			                        'total' => 0,
						            'on_time' => 0,
						            'missed' => 0,
						            'defaulter'=> 0,
						            'lost_to_followup'=> 0);
		$gender_adherence['female']  = array( 
			                            'total' => 0,
							            'on_time' => 0,
							            'missed' => 0,
							            'defaulter'=> 0,
							            'lost_to_followup'=> 0);

		$age_adherence['<15']  = array( 
			                        'total' => 0,
						            'on_time' => 0,
						            'missed' => 0,
						            'defaulter'=> 0,
						            'lost_to_followup'=> 0);
		$age_adherence['15_24']  = array( 
			                            'total' => 0,
							            'on_time' => 0,
							            'missed' => 0,
							            'defaulter'=> 0,
							            'lost_to_followup'=> 0);

		$age_adherence['>24']  = array( 
			                            'total' => 0,
							            'on_time' => 0,
							            'missed' => 0,
							            'defaulter'=> 0,
							            'lost_to_followup'=> 0);


        
        /*
         * Get all appointments for a patient in selected period
         * For each appointment, get corresponding visit that is equal or greater than date of appointment
         * e.g. if appointment is 2014-09-01 and visits for this period are 2014-09-03, 2014-09-15, use 2014-09-03
         * Calculate the difference of days between those two dates and find adherence
         */
       $sql = "SELECT 
                    pa.appointment,
                    pa.patient,
                    IF(UPPER(rst.Name) ='ART','art','non_art') as service,
        		    IF(UPPER(g.name) ='MALE','male','female') as gender,
        		    IF(FLOOR(DATEDIFF(CURDATE(),p.dob)/365)<15,'<15', IF(FLOOR(DATEDIFF(CURDATE(),p.dob)/365) >= 15 AND FLOOR(DATEDIFF(CURDATE(),p.dob)/365) <= 24,'15_24','>24')) as age
                FROM patient_appointment pa
                LEFT JOIN patient p ON p.patient_number_ccc = pa.patient
                LEFT JOIN regimen_service_type rst ON rst.id = p.service
                LEFT JOIN gender g ON g.id = p.gender 
                WHERE pa.appointment 
                BETWEEN '$start_date'
                AND '$end_date'
                GROUP BY pa.patient,pa.appointment
                ORDER BY pa.appointment";
        $query = $this ->db ->query($sql);
        $results = $query -> result_array();
        if($results)
        {   
        	foreach($results as $result){
        		$patient = $result['patient'];
        		$appointment = $result['appointment'];
        		$service = $result['service'];
	            $gender = $result['gender'];
	            $age = $result['age'];

            	$sql = "SELECT 
        		            DATEDIFF('$appointment',pv.dispensing_date) as no_of_days
	                    FROM v_patient_visits pv
	                    WHERE pv.patient_id='$patient'
	                    AND pv.dispensing_date >= '$appointment'
	                    GROUP BY pv.patient_id,pv.dispensing_date
	                    ORDER BY pv.dispensing_date ASC
	                    LIMIT 1";
	            $query = $this ->db ->query($sql);
	            $results = $query -> result_array();

	            $period = 90;

	            if($results)
	            {
	            	$period = $results[0]['no_of_days'];
	            }

		        if($type == "overview")
	            {
		            //Add period to array
		            if($period <= 3){
		               $ontime++;
	                   $adherence['on_time'] = $ontime;
		            }
		            else if($period > 3 && $period <= 14){
		               $missed++;
		               $adherence['missed'] = $missed++;
		            }
		            else if($period >= 15 && $period <= 89){
		               $defaulter++;
		               $adherence['defaulter'] = $defaulter;
		            }
		            else{
		               $lost_to_followup++;
		               $adherence['lost_to_followup'] = $lost_to_followup;
		            } 
		            $overview_total++;
		        }
		        else if($type == "service")
	            {         
               	    //Add period to array
		            if($period <= 3){
		               $ontime++;
	                   $art_adherence[$service]['on_time'] = $ontime;
		            }
		            else if($period > 3 && $period <= 14){
		               $missed++;
		               $art_adherence[$service]['missed'] = $missed++;
		            }
		            else if($period >= 15 && $period <= 89){
		               $defaulter++;
		               $art_adherence[$service]['defaulter'] = $defaulter;
		            }
		            else{
		               $lost_to_followup++;
		               $art_adherence[$service]['lost_to_followup'] = $lost_to_followup;
		            } 

	            } 
	            else if($type == "gender")
	            {         
               	    //Add period to array
		            if($period <= 3){
		               $ontime++;
	                   $gender_adherence[$gender]['on_time'] = $ontime;
		            }
		            else if($period > 3 && $period <= 14){
		               $missed++;
		               $gender_adherence[$gender]['missed'] = $missed++;
		            }
		            else if($period >= 15 && $period <= 89){
		               $defaulter++;
		               $gender_adherence[$gender]['defaulter'] = $defaulter;
		            }
		            else{
		               $lost_to_followup++;
		               $gender_adherence[$gender]['lost_to_followup'] = $lost_to_followup;
		            } 
	            } 
	            else if($type == "age")
	            {         
               	    //Add period to array
		            if($period <= 3){
		               $ontime++;
	                   $age_adherence[$age]['on_time'] = $ontime;
		            }
		            else if($period > 3 && $period <= 14){
		               $missed++;
		               $age_adherence[$age]['missed'] = $missed++;
		            }
		            else if($period >= 15 && $period <= 89){
		               $defaulter++;
		               $age_adherence[$age]['defaulter'] = $defaulter;
		            }
		            else{
		               $lost_to_followup++;
		               $age_adherence[$age]['lost_to_followup'] = $lost_to_followup;
		            }
	            } 
            }

	        if($type == "overview")
		    {   
		    	$adherence['total'] = $overview_total;
	            $data_array = $adherence;
		    }
		    else if($type == "service")
		    {   
				foreach ($art_adherence as $column=>$values) {
					foreach ($values as $value) {
						if($column == "art")
			            {
	                       $art_total+=$value;
			            }
			            else
			            {
	                       $non_art_total+=$value;
			            }
					}
				}

				$art_adherence['art']['total'] = $art_total;	
				$art_adherence['non_art']['total'] = $non_art_total;            
				$data_array = $art_adherence;
		    }
		    else if($type == "gender")
		    {   
                foreach ($gender_adherence as $column=>$values) {
					foreach ($values as $value) {
						if($column == "male")
			            {
	                       $male_total+=$value;
			            }
			            else
			            {
	                       $female_total+=$value;
			            }
					}
				}

		    	$gender_adherence['male']['total'] = $male_total;	
				$gender_adherence['female']['total'] = $female_total; 
	            $data_array = $gender_adherence;
		    }

		    else if($type == "age")
		    {   
		    	foreach ($age_adherence as $column=>$values) {
					foreach ($values as $value) {
						if($column == "<15")
			            {
	                       $fifteen_total+=$value;
			            }
			            else if($column == "15_24")
			            {
	                       $over_fifteen_total+=$value;
			            }
			            else
			            {
	                       $twenty_four_total+=$value;
			            }
					}
				}
				$age_adherence['<15']['total'] = $fifteen_total;	
				$age_adherence['15_24']['total'] = $over_fifteen_total; 
				$age_adherence['>24']['total'] = $twenty_four_total; 	            
				$data_array = $age_adherence;
		    }

		    foreach($data_array as $index => $mydata)
		    {
		    	if($type == 'overview')
		    	{   
		    		$main_array = array();
		    		$temp_array['name'] = "Status";
		    	    $temp_array['data'] = array_values($data_array);
		    	    $main_array[]=$temp_array;
		    	}
		    	else{
		    	    $temp_array['name'] = $index;
		    	    $temp_array['data'] = array_values($mydata);
		    	    $main_array[]=$temp_array;
		    	}
		    }
        }
		//chart data
		$resultArray = json_encode($main_array);
		$categories = json_encode(array('Total','On Time','Missed','Defaulter','Lost to Followup'));

		//chart settings
		$data['resultArraySize'] = 6;
		$data['container'] = 'chart_sales_'.$type;
		$data['chartType'] = 'column';
		$data['title'] = 'Chart';
		$data['chartTitle'] = 'Adherence By '.ucwords($type).' Between '.date('d/M/Y', strtotime($start_date)).' And '.date('d/M/Y', strtotime($end_date));
		$data['categories'] = $categories;
		$data['xAxix'] = 'Status';
		$data['suffix']= '';
		$data['yAxix'] = 'Totals';
		$data['resultArray'] = $resultArray;
		$this -> load -> view('graph_v', $data);
	}

	public function patients_nonadherence($start_date = "", $end_date = "") {
		$data['from'] = $start_date;
		$data['to'] = $end_date;
		$start_date = date('Y-m-d', strtotime($start_date));
		$end_date = date('Y-m-d', strtotime($end_date));
		$facility_code = $this -> session -> userdata('facility');
		$sql = "SELECT na.id,na.name,pv.gender,
				(CASE WHEN pv.age<=15 THEN 'Child'
				      WHEN pv.age>15 THEN 'Adult'
				      ELSE '' END) as age FROM non_adherence_reasons na LEFT JOIN
				(SELECT p_v.id,p_v.patient_id,p_v.dispensing_date,p_v.non_adherence_reason,p.gender,FLOOR( DATEDIFF( curdate( ) , p.dob ) /365 ) AS age FROM `patient_visit` p_v 
				LEFT JOIN patient p ON p.patient_number_ccc=p_v.patient_id
				WHERE p_v.dispensing_date BETWEEN '$start_date' AND '$end_date' AND p_v.active='1') as pv
				ON pv.non_adherence_reason=na.id ORDER BY na.id DESC  ";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		$uniqueNonAdherence = array_unique(array_map(function($i) {
			return $i['id'];
		}, $results));

		$tot_adult_male = 0;
		$tot_a_male = 0;
		$total_adult_female = 0;
		$tot_a_female = 0;
		$total_child_male = 0;
		$tot_c_male = 0;
		$total_child_female = 0;
		$tot_c_female = 0;
		$check_id = 0;
		$x = 0;
		$y = 0;
		$c = count($results);
		$dyn_table = "<table border='1' cellpadding='5' class='dataTables'>
		<thead>
			<tr>
				<th >Non Adherence Reason</th>
				<th>Adult</th><th></th><th>Children</th><th></th></tr>
				<tr><th></th><th>Male</th><th>Female</th><th>Male</th><th>Female</th>
			</tr></thead><tbody>";
		foreach ($results as $value) {
			$y++;
			if ($check_id != $value['id']) {//Check if new row

				if ($check_id != 0) {
					$dyn_table .= "<td>" . $tot_adult_male . "</td><td>" . $total_adult_female . "</td><td>" . $total_child_male . "</td><td>" . $total_child_female . "</td></tr>";
					$tot_adult_male = 0;
					$total_adult_female = 0;
					$total_child_male = 0;
					$total_child_female = 0;
				}

				$dyn_table .= "<tr><td>" . strtoupper($value['name']) . "</td>";
				//Non adherence Name
				$check_id = $value['id'];

			}
			if ($value['age'] == "Adult") {
				if ($value['gender'] == "1") {//Male
					$tot_adult_male++;
					$tot_a_male++;
				} else if ($value['gender'] == "2") {//Female
					$total_adult_female++;
					$tot_a_female++;
				}
			} else if ($value['age'] == "Child") {
				if ($value['gender'] == "1") {//Male
					$total_child_male++;
					$tot_c_male++;
				} else if ($value['gender'] == "2") {//Female
					$total_child_female++;
					$tot_c_female++;
				}
			}
			//Check if last row from array to append last row in table
			if ($c == $y) {
				$dyn_table .= "<td>" . $tot_adult_male . "</td><td>" . $total_adult_female . "</td><td>" . $total_child_male . "</td><td>" . $total_child_female . "</td></tr>";

			}

		}
		$dyn_table .= "<tfoot><td>Total</td><td>" . $tot_a_male . "</td><td>" . $tot_a_female . "</td><td>" . $tot_c_male . "</td><td>" . $tot_c_female . "</td></tfoot></table>";
		$data['dyn_table'] = $dyn_table;
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "early_warning_report_select";
		$data['selected_report_type'] = "Early Warning Indicators";
		$data['report_title'] = "Patients Non Adherence Summary";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/patients_nonadherence_v';
		$this -> load -> view('template', $data);
	}

	public function getFacilityConsumption($start_date = "", $end_date = "") {
		$data['from'] = $start_date;
		$data['to'] = $end_date;
		$start_date = date('Y-m-d', strtotime($start_date));
		$end_date = date('Y-m-d', strtotime($end_date));
		$facility_code = $this -> session -> userdata('facility');
		$consumption_totals = array();
		$row_string = "";
		$drug_total = 0;
		$total = 0;
		$overall_pharmacy_drug_qty = 0;
		$overall_store_drug_qty = 0;
		$pharmacy_drug_qty_percentage = "";
		$store_drug_qty_percentage = "";
		$drug_total_percentage = "";
		

		//Select total consumption at facility
		$sql = "select sum(quantity_out) as total 
				from drug_stock_movement 
				where transaction_date between '$start_date' and '$end_date' and facility='$facility_code' ";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			$total = $results[0]['total'];
		}

		//Select total consumption at facility per drug
		$sql = "select dsm.drug,d.drug as Name,d.pack_size,du.Name as unit,sum(dsm.quantity_out) as qty 
				from drug_stock_movement dsm 
				left join drugcode d on dsm.drug=d.id 
				left join drug_unit du on d.unit=du.id 
				where dsm.transaction_date between '$start_date' and '$end_date' 
				and dsm.facility='$facility_code' and dsm.drug!=''
				group by dsm.drug";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			$row_string .= "<table border='1' cellpadding='5' class='dataTables'>
			<thead>
			<tr>
				<th >Drug</th>
				<th >Unit</th>
				<th >PackSize</th>
				<th >Total(units)</th>
				<th >%</th>
				<th >Pharmacy(units)</th>
				<th >%</th>
				<th > Store(units)</th>
				<th >%</th>
			</tr>
			</thead>
			<tbody>
			";
			foreach ($results as $result) {
				$consumption_totals[$result['drug']] = $result['qty'];
				$current_drug = $result['drug'];
				$current_drugname = $result['Name'];
				$unit = $result['unit'];
				$pack_size = $result['pack_size'];
				$drug_total = $result['qty'];
				$drug_total_percentage = number_format(($drug_total / $total) * 100, 1);
				$row_string .= "<tr><td><b>$current_drugname</b></td><td><b>$unit</b></td><td><b>$pack_size</b></td><td>" . number_format($drug_total) . "</td><td>$drug_total_percentage</td>";
				//Select consumption at pharmacy
				$sql = "select drug,sum(quantity_out) as qty 
						from drug_stock_movement 
						where transaction_date between '$start_date' and '$end_date' 
						and facility='$facility_code' and source='$facility_code' and source=destination 
						and drug='$current_drug' group by drug";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				if ($results) {
					foreach ($results as $result) {
						$total_pharmacy_drug_qty = $result['qty'];
						$overall_pharmacy_drug_qty += $total_pharmacy_drug_qty;
						@$pharmacy_drug_qty_percentage = number_format((@$total_pharmacy_drug_qty / @$drug_total) * 100, 1);
						if ($result['drug'] != null) {
							$row_string .= "<td>" . number_format($total_pharmacy_drug_qty) . "</td><td>$pharmacy_drug_qty_percentage</td>";
						}
					}
				} else {
					$row_string .= "<td>-</td><td>-</td>";
				}
				//Select Consumption at store
				$sql = "select drug,sum(quantity_out) as qty 
						from drug_stock_movement 
						where transaction_date 
						between '$start_date' and '$end_date' and (facility='$facility_code' or destination='$facility_code') 
						and source !=destination and drug='$current_drug' group by drug";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				if ($results) {
					foreach ($results as $result) {
						$total_store_drug_qty = $result['qty'];
						$overall_store_drug_qty += $total_store_drug_qty;
						//If drug total ==0
						if ($drug_total == 0) {
							$store_drug_qty_percentage = "";
						} else {
							$store_drug_qty_percentage = number_format(($total_store_drug_qty / $drug_total) * 100, 1);
						}

						if ($result['drug'] != null) {
							$row_string .= "<td>" . number_format($total_store_drug_qty) . "</td><td>$store_drug_qty_percentage</td>";
						}
					}
				} else {
					$row_string .= "<td>-</td><td>-</td>";
				}
				$row_string .= "</tr>";
			}
			$row_string .= "</tbody><tfoot><tr><td><b>Totals(units):</b></td><td></td><td></td><td><b>" . number_format($total) . "</b></td><td><b>100</b></td><td><b>" . number_format($overall_pharmacy_drug_qty) . "</b></td><td><b>" . number_format(($overall_pharmacy_drug_qty / $total) * 100, 1) . "</b></td><td><b>" . number_format($overall_store_drug_qty) . "</b></td><td><b>" . number_format(($overall_store_drug_qty / $total) * 100, 1) . "</b></td></tr></tfoot>";
			$row_string .= "</table>";
		}
		$data['dyn_table'] = $row_string;
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "drug_inventory_report_row";
		$data['selected_report_type'] = "Drug Inventory";
		$data['report_title'] = "Stock Consumption";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/stock_consumption_v';
		$this -> load -> view('template', $data);

	}

	public function patients_disclosure($start_date = "", $end_date = "") {
		$data['from'] = $start_date;
		$data['to'] = $end_date;
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "standard_report_select";
		$data['selected_report_type'] = "Patient Status &amp; Disclosure";
		$data['report_title'] = "Summary";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/patient_disclosure_v';
		$this -> load -> view('template', $data);
	}

	public function disclosure_chart($start_date = "", $end_date = "") {
		$data['from'] = $start_date;
		$data['to'] = $end_date;
		$heading = "Patient Disclosure Between $start_date and $end_date";
		$start_date = date('Y-m-d', strtotime($start_date));
		$end_date = date('Y-m-d', strtotime($end_date));
		$facility_code = $this -> session -> userdata('facility');
		$sql = "SELECT gender, disclosure, count( * ) AS total FROM `patient` LEFT JOIN patient_status ps ON ps.id=current_status where date_enrolled between '$start_date' and '$end_date' AND ps.Name like '%active%' and partner_status = '2' AND gender != '' AND disclosure != '2' AND facility_code='$facility_code' GROUP BY gender, disclosure";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		$strXML = array();
		$strXML['Male Disclosure(NO)'] = 0;
		$strXML['Male Disclosure(YES)'] = 0;
		$strXML['Female Disclosure(NO)'] = 0;
		$strXML['Female Disclosure(YES)'] = 0;
		if ($results) {
			foreach ($results as $result) {
				if ($result['gender'] == '1' && $result['disclosure'] == 0) {
					$strXML['Male Disclosure(NO)'] = (int)$result['total'];
				} else if ($result['gender'] == '1' && $result['disclosure'] == 1) {
					$strXML['Male Disclosure(YES)'] = (int)$result['total'];
				} else if ($result['gender'] == '2' && $result['disclosure'] == 0) {
					$strXML['Female Disclosure(NO)'] = (int)$result['total'];
				} else if ($result['gender'] == '2' && $result['disclosure'] == 1) {
					$strXML['Female Disclosure(YES)'] = (int)$result['total'];
				}

			}
		}
		$strXML = implode($strXML, ",");
		$strXML = array_map('intval', explode(",", $strXML));
		$resultArray = array();
		$nameArray = array("Male Disclosure(NO)", "Male Disclosure(YES)", "Female Disclosure(NO)", "Female Disclosure(YES)");
		$resultArray[] = array('name' => "Disclosure Status", 'data' => $strXML);
		$categories = json_encode($nameArray);
		$resultArray = json_encode($resultArray);
		$data['resultArraySize'] = 6;
		$data['container'] = "chart_div";
		$data['chartType'] = 'bar';
		$data['chartTitle'] = 'Patients Disclosure';
		$data['yAxix'] = 'Status';
		$data['categories'] = $categories;
		$data['resultArray'] = $resultArray;
		$this -> load -> view('chart_v', $data);

	}

	public function getTBPatients($start_date = "", $end_date = "") {
		$data['from'] = $start_date;
		$data['to'] = $end_date;
		$start_date = date('Y-m-d', strtotime($start_date));
		$end_date = date('Y-m-d', strtotime($end_date));
		$facility_code = $this -> session -> userdata('facility');
		$one_adult_male = 0;
		$one_child_male = 0;
		$one_adult_female = 0;
		$one_child_female = 0;
		$two_adult_male = 0;
		$two_child_male = 0;
		$two_adult_female = 0;
		$two_child_female = 0;
		$three_adult_male = 0;
		$three_child_male = 0;
		$three_adult_female = 0;
		$three_child_female = 0;

		$sql = "update patient set tbphase='0' where tbphase='un' or tbphase=''";
		$query = $this -> db -> query($sql);
		$sql = "select gender,FLOOR(DATEDIFF(curdate(),dob)/365) as age,tbphase from patient LEFT JOIN patient_status ps ON ps.id=current_status where date_enrolled between '$start_date' and '$end_date' AND ps.Name like '%active%' and facility_code='$facility_code' and gender !='' and tb='1' and tbphase !='0'";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		$strXML = array();
		if ($results) {
			foreach ($results as $result) {
				if ($result['tbphase'] == 1) {
					if ($result['gender'] == 1) {
						if ($result['age'] >= 15) {
							$one_adult_male++;
						} else if ($result['age'] < 15) {
							$one_child_male++;
						}
					} else if ($result['gender'] == 2) {
						if ($result['age'] >= 15) {
							$one_adult_female++;
						} else if ($result['age'] < 15) {
							$one_child_female++;
						}
					}
				} else if ($result['tbphase'] == 2) {
					if ($result['gender'] == 1) {
						if ($result['age'] >= 15) {
							$two_adult_male++;
						} else if ($result['age'] < 15) {
							$two_child_male++;
						}
					} else if ($result['gender'] == 2) {
						if ($result['age'] >= 15) {
							$two_adult_female++;
						} else if ($result['age'] < 15) {
							$two_child_female++;
						}
					}
				} else if ($result['tbphase'] == 3) {
					if ($result['gender'] == 1) {
						if ($result['age'] >= 15) {
							$three_adult_male++;
						} else if ($result['age'] < 15) {
							$three_child_male++;
						}
					} else if ($result['gender'] == 2) {
						if ($result['age'] >= 15) {
							$three_adult_female++;
						} else if ($result['age'] < 15) {
							$three_child_female++;
						}
					}
				}
			}
		}
		$dyn_table = "<table border='1' cellpadding='5' class='dataTables'><thead>
			<tr>
				<th></th><th>Adults</th><th></th><th>Children</th><th></th>
			</tr>
			<tr><th>Stages</th><th>No. of Males(TB)</th><th>No. of Females(TB)</th><th>No. of Males(TB)</th><th>No. of Females(TB)</th></tr></thead><tbody>";
		$dyn_table .= "<tr><td>Intensive</td><td>" . number_format($one_adult_male) . "</td><td>" . number_format($one_adult_female) . "</td><td>" . number_format($one_child_male) . "</td><td>" . number_format($one_child_female) . "</td></tr>";
		$dyn_table .= "<tr><td>Continuation</td><td>" . number_format($two_adult_male) . "</td><td>" . number_format($two_adult_female) . "</td><td>" . number_format($two_child_male) . "</td><td>" . number_format($two_child_female) . "</td></tr>";
		$dyn_table .= "<tr><td>Completed</td><td>" . number_format($three_adult_male) . "</td><td>" . number_format($three_adult_female) . "</td><td>" . number_format($three_child_male) . "</td><td>" . number_format($three_child_female) . "</td></tr>";
		$dyn_table .= "</tbody><tfoot><tr><td><b>TOTALS</b></td><td><b>" . number_format($one_adult_male + $two_adult_male + $three_adult_male) . "</b></td><td><b>" . number_format($one_adult_female + $two_adult_female + $three_adult_female) . "</b></td><td><b>" . number_format($one_child_male + $two_child_male + $three_child_male) . "</b></td><td><b>" . number_format($one_child_female + $two_child_female + $three_child_female) . "</b></td></tr>";
		$dyn_table .= "</tfoot></table>";
		$data['dyn_table'] = $dyn_table;
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "standard_report_row";
		$data['selected_report_type'] = "Standard Reports";
		$data['report_title'] = "TB Stages Summary";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/tb_stages_v';
		$this -> load -> view('template', $data);
	}

	public function getFamilyPlanning($start_date = "") {
		$data['from'] = $start_date;
		$start_date = date('Y-m-d', strtotime($start_date));
		//$end_date = date('Y-m-d', strtotime($end_date));
		$facility_code = $this -> session -> userdata('facility');
		$arr = array();
		$total = 0;
		$sql = "select fplan from patient LEFT JOIN patient_status ps ON ps.id=current_status where date_enrolled <= '$start_date' AND ps.Name like '%active%' and gender='2' and gender !='' and facility_code='$facility_code' AND fplan != '' AND fplan != 'null' AND FLOOR(DATEDIFF(curdate(),dob)/365)>15 AND FLOOR(DATEDIFF(curdate(),dob)/365)<=49";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();

		if ($results) {
			$dyn_str = "<table border='1' id='patient_listing' class='dataTables' cellpadding='5'><thead><tr><th>Method</th><th>No. Of Women on Method</th><th>Percentage Proportion(%)</th></tr></thead><tbody>";
			foreach ($results as $result) {
				if (strstr($result['fplan'], ',', true)) {
					$values = explode(",", $result['fplan']);
					foreach ($values as $value) {
						$arr[] = $value;
					}
				} else {
					$arr[] = $result['fplan'];
				}

			}
			$family_planning = array_count_values($arr);
			foreach ($family_planning as $family_plan => $index) {
				$sql = "select name from family_planning where indicator='$family_plan'";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				if ($results) {
					foreach ($results as $result) {
						$family[$result['name']] = $index;
					}
				}
				$total += $index;
			}

			foreach ($family as $farm => $index) {
				$dyn_str .= "<tr><td>" . $farm . "</td><td>" . $index . "</td><td>" . number_format(($index / $total) * 100, 1) . "%</td></tr>";
			}
			$dyn_str .= "</tbody><tfoot><tr><td><b>TOTALS</b></td><td><b>$total</b></td><td><b>100%</b></td></tr>";
			$dyn_str .= "</tfoot></table>";
		} else {
			$dyn_str = "<h4 style='text-align: center'><span >No Data Available</span></h4>";
		}

		$data['dyn_table'] = $dyn_str;
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "standard_report_row";
		$data['selected_report_type'] = "Standard Reports";
		$data['report_title'] = "Family Planning Summary";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/family_planning_v';
		$this -> load -> view('template', $data);

	}

	public function getIndications($start_date = "", $end_date = "") {
		$data['from'] = $start_date;
		$data['to'] = $end_date;
		$start_date = date('Y-m-d', strtotime($start_date));
		$end_date = date('Y-m-d', strtotime($end_date));
		$facility_code = $this -> session -> userdata('facility');
		$sql = "select CONCAT_WS(' | ',oi.indication,oi.name) as indication_name,IF(FLOOR(DATEDIFF(curdate(),p.dob)/365)>15 and p.gender='1',count(*),'0') as adult_male,IF(FLOOR(DATEDIFF(curdate(),p.dob)/365)>15 and p.gender='2',count(*),'0') as adult_female,IF(FLOOR(DATEDIFF(curdate(),p.dob)/365)<=15 ,count(*),'0') as child from (select patient_id,indication from patient_visit where dispensing_date between '$start_date' and '$end_date' and facility='$facility_code' and indication !='0')as pv left join patient p on p.patient_number_ccc=pv.patient_id,opportunistic_infection oi where (oi.id=pv.indication or oi.indication=pv.indication) group by indication_name";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		$total = 0;
		$children = 0;
		$adult_male = 0;
		$adult_female = 0;
		$overall_adult_male = 0;
		$overall_adult_female = 0;
		$overall_children = 0;
		$dyn_table = "";
		$dyn_table .= "<table id='patient_listing' border='1' cellpadding='5' class='dataTables'><thead><tr><th>Indication</th><th>Adult Male</th><th>Adult Female</th><th>Children</th></tr></thead>";
		if ($results) {
			$dyn_table .= "<tbody>";
			foreach ($results as $result) {
				$indication = $result['indication_name'];
				$adult_male = $result['adult_male'];
				$adult_female = $result['adult_female'];
				$children = $result['child'];
				$overall_adult_male += $adult_male;
				$overall_adult_female += $adult_female;
				$overall_children += $children;
				$dyn_table .= "<tr><td><b>$indication <b></td><td>" . number_format($adult_male) . "</td><td>" . number_format($adult_female) . "</td><td>" . number_format($children) . "</td></tr>";

			}
			$total = $overall_adult_male + $overall_adult_female + $overall_children;
			$total = number_format($total);
			$dyn_table .= "</tbody><tfoot><tr><td><b>TOTALS ($total) </b></td><td><b>" . number_format($overall_adult_male) . "</b></td><td><b>" . number_format($overall_adult_female) . "</b></td><td><b>" . number_format($overall_children) . "</b></td></tr>";
			$dyn_table .= "</tfoot>";
		}
		$dyn_table .= "</table>";
		$data['dyn_table'] = $dyn_table;
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "standard_report_row";
		$data['selected_report_type'] = "Standard Reports";
		$data['report_title'] = "Patient Indication Summary";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/patient_indication_v';
		$this -> load -> view('template', $data);
	}

	public function getChronic($start_date = "") {
		$data['from'] = $start_date;
		$start_date = date('Y-m-d', strtotime($start_date));
		$facility_code = $this -> session -> userdata('facility');
		$total = 0;
		$total_male_tb = 0;
		$total_female_tb = 0;
		$total_children_tb = 0;
		$adult_male = array();
		$adult_female = array();
		$child = array();
		$sql = "SELECT other_illnesses, FLOOR( DATEDIFF( curdate( ) , dob ) /365 ) AS age,gender FROM patient LEFT JOIN patient_status ps ON ps.id=current_status WHERE date_enrolled <= '$start_date' AND ps.Name like '%active%' AND gender != '' AND facility_code = '$facility_code' AND other_illnesses != '' AND other_illnesses != ',' AND other_illnesses != 'null'";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				if (trim(strtoupper($result['other_illnesses'])) != null && trim(strtoupper($result['other_illnesses'])) != 'NULL') {

					if (strstr($result['other_illnesses'], ',', true)) {
						$values = explode(",", $result['other_illnesses']);
						foreach ($values as $value) {
							$arr[] = trim(strtoupper($value));
						}
					} else {
						$arr[] = trim(strtoupper($result['other_illnesses']));
					}
					if ($result['gender'] == 1) {//Check Male
						if ($result['age'] >= 15) {//Check Adult
							if (strstr(trim($result['other_illnesses']), ',', true)) {
								$values = explode(",", $result['other_illnesses']);
								foreach ($values as $value) {
									$adult_male[] = trim(strtoupper($value));
								}
							} else {
								$adult_male[] = trim(strtoupper($result['other_illnesses']));
							}
						} else if ($result['age'] < 15) {//Check Child
							if (strstr(trim($result['other_illnesses']), ',', true)) {
								$values = explode(",", $result['other_illnesses']);
								foreach ($values as $value) {
									$child[] = trim(strtoupper($value));
								}
							} else {
								$child[] = trim(strtoupper($result['other_illnesses']));
							}
						}

					} else if ($result['gender'] == 2) {//Check Female
						if ($result['age'] >= 15) {//Check Adult
							if (strstr(trim($result['other_illnesses']), ',', true)) {
								$values = explode(",", $result['other_illnesses']);
								foreach ($values as $value) {
									$adult_female[] = trim(strtoupper($value));
								}
							} else {
								$adult_female[] = trim(strtoupper($result['other_illnesses']));
							}
						} else if ($result['age'] < 15) {//Check Child
							if (strstr(trim($result['other_illnesses']), ',', true)) {
								$values = explode(",", $result['other_illnesses']);
								foreach ($values as $value) {
									$child[] = trim(strtoupper($value));
								}
							} else {
								$child[] = trim(strtoupper($result['other_illnesses']));
							}
						}
					}

				}
			}
			$other_illnesses = array_count_values($arr);
			$other_illnesses_male = array_count_values($adult_male);
			$other_illnesses_female = array_count_values($adult_female);
			$other_illnesses_child = array_count_values($child);
			$values = array();

			foreach ($other_illnesses as $other_illness => $index) {
				if (array_key_exists($other_illness, $other_illnesses_male)) {
					$values[$other_illness]['male'] = $index;
				} else {
					$values[$other_illness]['male'] = 0;
				}
				if (array_key_exists($other_illness, $other_illnesses_female)) {
					$values[$other_illness]['female'] = $index;
				} else {
					$values[$other_illness]['female'] = 0;
				}
				if (array_key_exists($other_illness, $other_illnesses_child)) {
					$values[$other_illness]['child'] = $index;
				} else {
					$values[$other_illness]['child'] = 0;
				}
				$total += $index;
			}
			foreach ($values as $value => $index) {
				foreach ($index as $key => $val) {
					$sql = "select * from other_illnesses where indicator='$value'";
					$query = $this -> db -> query($sql);
					$results = $query -> result_array();
					if ($results) {
						foreach ($results as $result) {
							$answer = strtoupper($result['name']);
						}
						$values[$answer][$key] = $val;
						unset($values[$value]);
					}
				}
			}
		}
		//Get TB Numbers
		$sql = "select FLOOR( DATEDIFF( curdate( ) , dob ) /365 ) AS age,gender from patient WHERE date_enrolled <= '$start_date'  AND gender != '' AND facility_code = '$facility_code' AND tb='1' AND dob !='' AND gender !=''";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				if ($result['age'] >= 15) {
					if ($result['gender'] == 1) {
						$total_male_tb++;
					} else if ($result['gender'] == 2) {
						$total_female_tb++;
					}
				} else if ($result['age'] < 15) {
					$total_children_tb++;
				}
			}
		}
		//Initialize tb
		$values['TB']['male'] = $total_male_tb;
		$values['TB']['female'] = $total_female_tb;
		$values['TB']['child'] = $total_children_tb;

		$overall_male = 0;
		$overall_female = 0;
		$overall_child = 0;

		$dyn_table = "<table border='1' cellpadding='5' class='dataTables'>
		<thead><tr><th>Chronic Diseases</th><th>Adult Male</th><th>Adult Female</th><th>Children</th></tr></thead><tbody>";

		foreach ($values as $value => $indices) {
			$dyn_table .= "<tr><td><b>$value</b></td>";
			foreach ($indices as $index => $newval) {
				if ($index == "male") {
					$overall_male += $newval;
				} else if ($index == "female") {
					$overall_female += $newval;
				} else if ($index == "child") {
					$overall_child += $newval;
				}

				$val = number_format($newval);
				$dyn_table .= "<td>$val</td>";
			}
			$dyn_table .= "</tr>";
		}
		$dyn_table .= "</tbody><tfoot><tr><td><b>TOTALS</b></td><td><b>" . number_format($overall_male) . "</b></td><td><b>" . number_format($overall_female) . "</b></td><td><b>" . number_format($overall_child) . "</b></td></tr>";
		$dyn_table .= "</tfoot></table>";
		$data['dyn_table'] = $dyn_table;
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "standard_report_row";
		$data['selected_report_type'] = "Standard Reports";
		$data['report_title'] = "Chronic Illnesses Summary";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/chronic_v';
		$this -> load -> view('template', $data);
	}

	public function getADR($start_date = "") {
		$data['from'] = $start_date;
		//$data['to'] = $end_date;
		$start_date = date('Y-m-d', strtotime($start_date));
		//$end_date = date('Y-m-d', strtotime($end_date));
		$facility_code = $this -> session -> userdata('facility');
		$male_adr = 0;
		$female_adr = 0;
		$male_noadr = 0;
		$female_noadr = 0;

		//Get Those With ADR
		$sql = "select gender,count(*)as total from patient LEFT JOIN patient_status ps ON ps.id=current_status WHERE date_enrolled <= '$start_date' AND ps.Name like '%active%' and facility_code='$facility_code' and adr !='' and adr !='null' and adr is not null and gender !='' group by gender";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				if ($result['gender'] == 1) {
					$male_adr = $result['total'];
				} else if ($result['gender'] == 2) {
					$female_adr = $result['total'];
				}
			}
		}

		//Get Those Without ADR
		$sql = "select gender,count(*)as total from patient WHERE date_enrolled <= '$start_date'  and facility_code='$facility_code' and adr ='' or adr ='null' or adr is  null and gender !='' group by gender";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				if ($result['gender'] == 1) {
					$male_noadr = $result['total'];
				} else if ($result['gender'] == 2) {
					$female_noadr = $result['total'];
				}
			}
		}

		$percentage_adr = 0;
		$percentage_noadr = 0;
		$total_adr_noadr = 0;
		$total_adr_noadr = $male_adr + $female_adr + $male_noadr + $female_noadr;
		if ($total_adr_noadr > 0) {
			$percentage_adr = (($male_adr + $female_adr) / ($total_adr_noadr)) * 100;
			$percentage_noadr = (($male_noadr + $female_noadr) / ($total_adr_noadr)) * 100;
		}

		$dyn_table = "<table border='1' cellpadding='5' class='dataTables'>";
		$dyn_table .= "<thead>";
		$dyn_table .= "<tr><th>Patients with Allergy</th><th></th><th>Patients without Allergy</th><th></th><th>Percentage with Allergy</th><th>Percentage without Allergy</th></tr>";
		$dyn_table .= "<tr><th>Male</th><th>Female</th><th>Male</th><th>Female</th><th>((Male +Female)/total)*100%</th><th>((Male +Female)/total)*100%</th></tr>";
		$dyn_table .= "</thead>";
		$dyn_table .= "<tbody>";
		$dyn_table .= "<tr><td>" . number_format($male_adr) . "</td><td>" . number_format($female_adr) . "</td><td>" . number_format($male_noadr) . "</td><td>" . number_format($female_noadr) . "</td><td>" . number_format($percentage_adr, 1) . "%</td><td>" . number_format($percentage_noadr, 1) . "%</td></tr>";
		$dyn_table .= "</tbody>";
		$dyn_table .= "</table>";
		$data['dyn_table'] = $dyn_table;
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "standard_report_row";
		$data['selected_report_type'] = "Standard Reports";
		$data['report_title'] = "Patient Allergies Summary";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/allergy_v';
		$this -> load -> view('template', $data);
	}

	public function getDrugsIssued($stock_type, $start_date = "", $end_date = "")
	{   
		$facility_code = $this->session->userdata("facility");
		$start_date = date('Y-m-d',strtotime($start_date));
		$end_date = date('Y-m-d',strtotime($end_date));
        
        //Get Drugs Received in Period
		$this->db->select("UPPER(d.drug) as drug_name,IF(ds.name IS NOT NULL,UPPER(ds.name),UPPER(dsm.source_destination)) as drug_source,SUM(dsm.quantity) as total")
		    ->from("drug_stock_movement dsm")
		    ->join("transaction_type t","t.id = dsm.transaction_type","LEFT")
			->join("drugcode d","d.id = dsm.drug","LEFT")
			->join("drug_source ds","ds.id = dsm.source_destination","LEFT")
			->where("dsm.transaction_date BETWEEN '$start_date' AND '$end_date'")
		    ->where("dsm.facility",$facility_code)
		    ->like("t.name","issue")
		    ->where("d.id IS NOT NULL")
		    ->group_by("d.drug,dsm.source_destination");
		$query = $this->db->get();
 		$results = $query->result_array();
 		
 		$sources = array();

 		if($results)
 		{
 			foreach ($results as $result) {
				$temp = array();
				$temp[$result['drug_source']] = $result['total'];
				$sources[] = $result['drug_source'];
				$drugs[$result['drug_name']][] = $temp;
			}
 		}

		//Select Unique Sources
		$sources = array_unique($sources);

		$temp = array();
         
        if($drugs)
        {
			//Loop through Drugs 
	    	foreach($drugs as $drug => $sources_data)
	    	{   
	    		$temp_data = array();
	    		//Map Drugs to Sources
	            foreach($sources as $source)
	            {   
	            	foreach($sources_data as $source_data)
	            	{   
	            		foreach($source_data as $name => $value)
	            		{
	            			if($source == $name)
			            	{
			                	$temp_data[$source] = $value;
			            	}
			            	else
			            	{
			                	$temp_data[$source] = 0;
			            	}
	            		}
	            	}
	            }

	            $temp[$drug] = $temp_data;
	    	}
	    }

    	$this -> load -> library('table');

		$tmpl = array('table_open' => '<table class="table table-bordered table-hover table-condensed dataTables" id="received_listing">');
		$initial = array('#','DRUGNAME');
		$columns = array_merge($initial,$sources);
		
		$this -> table -> set_template($tmpl);
		$this -> table -> set_heading($columns);

        foreach ($temp as $drug => $quantities) {
        	$result = array();
        	$result['DRUGNAME'] = $drug;

        	foreach($quantities as $source_name =>$quantity)
        	{	
        		$result[$source_name] = number_format($quantity);
        	}
			$this -> table -> add_row($result);
		}

		$ccc = CCC_store_service_point::getCCC($stock_type);
		$data['transaction_type'] = $ccc['Name'];
		$data['from'] = $start_date;
		$data['to'] = $end_date;
		$data['dyn_table'] = $this -> table -> generate();
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "drug_inventory_report_row";
		$data['selected_report_type'] = "Stock Consumption";
		$data['report_title'] = "Stock Consumption";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/drugissued_v';
		$this -> load -> view('template', $data);
	}

	public function getDrugsIssued_old($stock_type, $start_date = "", $end_date = "") {
		$data['from'] = $start_date;
		$data['to'] = $end_date;
		$start_date = date('Y-m-d', strtotime($start_date));
		$end_date = date('Y-m-d', strtotime($end_date));
		$facility_code = $this -> session -> userdata('facility');
		$facilty_value = "";
		$param = "";
		$facilty_value = "dsm.ccc_store_sp=$stock_type";
		$ccc = CCC_store_service_point::getCCC($stock_type);
		$data['transaction_type'] = $ccc['Name'];
		
		$sql = "select d.id,d.drug,du.Name as unit,d.pack_size,SUM(dsm.quantity_out) as total from drug_stock_movement dsm 
				LEFT JOIN transaction_type t ON t.id=dsm.transaction_type 
				LEFT JOIN drugcode d ON d.id=dsm.drug 
				LEFT JOIN drug_unit du ON du.id=d.unit 
				where dsm.transaction_date between '$start_date' and '$end_date' and $facilty_value and dsm.facility='$facility_code' 
				AND t.name LIKE '%Issued To%' AND d.id IS  NOT NULL GROUP BY d.drug";
		//echo $sql;die();
		$query = $this -> db -> query($sql);
		$dest_array =Drug_destination::getAllHydrate();
		$all_other_ccc_stores = CCC_store_service_point::getAllBut($stock_type);
		
		$dyn_table = "<table border='1' class='dataTables' cellpadding='5'>";
		$dyn_table .= "<thead>
						<tr><th>Drug Name</th>
					";
		$dest_array =array_merge($all_other_ccc_stores,$dest_array);
		//echo json_encode($dest_array);die();
		foreach ($dest_array as $value) {
			$dyn_table .= "<th>" . $value['Name'] . "</th>";
		}
		$dyn_table .= "</tr>
						</thead>
						<tbody>";

		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				$dyn_table .= "<tr><td>" . $result['drug'] . "</td>";
				//Get all destinations for that drug
			    $get_drugs = "
			    			SELECT table1.name,table1.total FROM
			    			(
								(
								SELECT csp.name as name,temp.total 
					              FROM ccc_store_service_point csp
					              LEFT JOIN 
					              (SELECT source_destination,SUM(dsm.quantity_out) as total 
					              	         FROM drug_stock_movement dsm 
					              	         LEFT JOIN transaction_type t ON t.id=dsm.transaction_type 
					              	         LEFT JOIN drugcode d ON d.id=dsm.drug 
					              	         LEFT JOIN drug_unit du ON du.id=d.unit 
					              	         WHERE dsm.transaction_date 
					              	         BETWEEN '$start_date' 
					              	         AND '$end_date' 
					              	         AND $facilty_value 
					              	         AND t.name LIKE '%Issued To%' 
					              	         AND dsm.drug='" . $result['id'] . "' 
					              	         GROUP BY source_destination) as temp ON temp.source_destination = csp.name
					              	         
					              WHERE csp.id !=$stock_type AND csp.active = 1 
								)
								UNION ALL
								(
								SELECT des.name as name,temp.total 
					              FROM drug_destination des  
					              LEFT JOIN (SELECT source_destination,SUM(dsm.quantity_out) as total 
					              	         FROM drug_stock_movement dsm 
					              	         LEFT JOIN transaction_type t ON t.id=dsm.transaction_type 
					              	         LEFT JOIN drugcode d ON d.id=dsm.drug 
					              	         LEFT JOIN drug_unit du ON du.id=d.unit 
					              	         WHERE dsm.transaction_date 
					              	         BETWEEN '$start_date' 
					              	         AND '$end_date' 
					              	         AND $facilty_value 
					              	         AND t.name LIKE '%Issued To%' 
					              	         AND dsm.drug='" . $result['id'] . "' 
					              	         GROUP BY source_destination) as temp ON temp.source_destination=des.id 
					              	         WHERE des.active=1
	                                         ORDER BY des.id ASC
								)
							) as table1
			    			";                       
				$get_dest = $this -> db -> query($get_drugs);
				$get_des_array = $get_dest -> result_array();
				if($get_des_array){
					foreach ($get_des_array as $value) {
						$total = $value['total'];
						if ($value['total'] == null) {
							$total = 0;
						}
						$dyn_table .= "<td>" . $total . "</td>";
					}
				}
				$dyn_table .= "</tr>";
			}
		} else {
			//$dyn_table .= "<tr><td colspan='4'>No Data Available</td></tr>";
		}
		$dyn_table .= "</tbody></table>";
		$data['dyn_table'] = $dyn_table;
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "drug_inventory_report_row";
		$data['selected_report_type'] = "Drug Inventory";
		$data['report_title'] = "Stock Consumption";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/drugissued_v';
		$this -> load -> view('template', $data);
	}


	public function get_lost_followup($from = "",$to ="")
	{
		$facility_code = $this->session->userdata("facility");
		$start_date = date('Y-m-d',strtotime($from));
		$end_date = date('Y-m-d',strtotime($to));
        
        //Get Patients Lost to Follow Up
		$this->db->select("p.patient_number_ccc as ccc_no,UPPER(CONCAT_WS(' ',p.first_name,CONCAT_WS(' ',p.other_name,p.last_name))) as person_name,ps.name as status,DATE_FORMAT(p.status_change_date,'%d/%b/%Y') as status_date",FALSE)
		    ->from("patient p")
		    ->join("patient_status ps","ps.id = p.current_status","LEFT")
			->where("p.status_change_date BETWEEN '$start_date' AND '$end_date'")
		    ->where("p.facility_code",$facility_code)
		    ->like("ps.name","lost")
		    ->where("p.patient_number_ccc IS NOT NULL")
		    ->group_by("p.id");
		$query = $this->db->get();
 		$results = $query->result_array();

 		$this -> load -> library('table');

		$tmpl = array('table_open' => '<table class="table table-bordered table-hover table-condensed dataTables" id="followup_listing">');
		$columns = array('#','CCC NO','PATIENT NAME','STATUS','DATE OF STATUS CHANGE');
		$this -> table -> set_template($tmpl);
		$this -> table -> set_heading($columns);

		foreach ($results as $result) 
		{
			$this -> table -> add_row($result);
		}

		$data['from'] = $from;
		$data['to'] = $to;
		$data['dyn_table'] = $this -> table -> generate();
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "early_warning_report_select";
		$data['selected_report_type'] = "Stock Consumption";
		$data['report_title'] = "Stock Consumption";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/lostfollowup_v';
		$this -> load -> view('template', $data);

	}

	public function getDrugsReceived($stock_type, $start_date = "", $end_date = "")
	{   
		$facility_code = $this->session->userdata("facility");
		$start_date = date('Y-m-d',strtotime($start_date));
		$end_date = date('Y-m-d',strtotime($end_date));
        
        //Get Drugs Received in Period
		$this->db->select("UPPER(d.drug) as drug_name,IF(ds.name IS NOT NULL,UPPER(ds.name),UPPER(dsm.source_destination)) as drug_source,SUM(dsm.quantity) as total")
		    ->from("drug_stock_movement dsm")
		    ->join("transaction_type t","t.id = dsm.transaction_type","LEFT")
			->join("drugcode d","d.id = dsm.drug","LEFT")
			->join("drug_source ds","ds.id = dsm.source_destination","LEFT")
			->where("dsm.transaction_date BETWEEN '$start_date' AND '$end_date'")
		    ->where("dsm.facility",$facility_code)
		    ->like("t.name","received")
		    ->where("d.id IS NOT NULL")
		    ->group_by("d.drug,dsm.source_destination");
		$query = $this->db->get();
 		$results = $query->result_array();

 		$sources = array();

 		if($results)
 		{
 			foreach ($results as $result) {
				$temp = array();
				$temp[$result['drug_source']] = $result['total'];
				$sources[] = $result['drug_source'];
				$drugs[$result['drug_name']][] = $temp;
			}
 		}

		//Select Unique Sources
		$sources = array_unique($sources);

		$temp = array();
         
        if($drugs)
        {
			//Loop through Drugs 
	    	foreach($drugs as $drug => $sources_data)
	    	{   
	    		$temp_data = array();
	    		//Map Drugs to Sources
	            foreach($sources as $source)
	            {   
	            	foreach($sources_data as $source_data)
	            	{   
	            		foreach($source_data as $name => $value)
	            		{
	            			if($source == $name)
			            	{
			                	$temp_data[$source] = $value;
			            	}
			            	else
			            	{
			                	$temp_data[$source] = 0;
			            	}
	            		}
	            	}
	            }

	            $temp[$drug] = $temp_data;
	    	}
	    }

    	$this -> load -> library('table');

		$tmpl = array('table_open' => '<table class="table table-bordered table-hover table-condensed dataTables" id="received_listing">');
		$initial = array('#','DRUGNAME');
		$columns = array_merge($initial,$sources);
		
		$this -> table -> set_template($tmpl);
		$this -> table -> set_heading($columns);

        foreach ($temp as $drug => $quantities) {
        	$result = array();
        	$result['DRUGNAME'] = $drug;

        	foreach($quantities as $source_name =>$quantity)
        	{	
        		$result[$source_name] = number_format($quantity);
        	}
			$this -> table -> add_row($result);
		}

		$ccc = CCC_store_service_point::getCCC($stock_type);
		$data['transaction_type'] = $ccc['Name'];
		$data['from'] = $start_date;
		$data['to'] = $end_date;
		$data['dyn_table'] = $this -> table -> generate();
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "drug_inventory_report_row";
		$data['selected_report_type'] = "Stock Consumption";
		$data['report_title'] = "Stock Consumption";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/drugreceived_v';
		$this -> load -> view('template', $data);
	}

	public function getDrugsReceived_old($stock_type, $start_date = "", $end_date = "") {
		
		$data['from'] = $start_date;
		$data['to'] = $end_date;
		$start_date = date('Y-m-d', strtotime($start_date));
		$end_date = date('Y-m-d', strtotime($end_date));
		$facility_code = $this -> session -> userdata('facility');
		$facilty_value = "";
		$param = "";
		$facilty_value = "dsm.ccc_store_sp=$stock_type";
		$ccc = CCC_store_service_point::getCCC($stock_type);
		$data['transaction_type'] = $ccc['Name'];
		
		$sql = "select d.id,d.drug,du.Name as unit,d.pack_size,SUM(dsm.quantity) as total from drug_stock_movement dsm 
				LEFT JOIN transaction_type t ON t.id=dsm.transaction_type 
				LEFT JOIN drugcode d ON d.id=dsm.drug 
				LEFT JOIN drug_unit du ON du.id=d.unit 
				where dsm.transaction_date between '$start_date' and '$end_date' and $facilty_value and dsm.facility='$facility_code' 
				AND t.name LIKE '%Received%' AND d.id IS  NOT NULL GROUP BY d.drug";
		//echo $sql;die();
		$query = $this -> db -> query($sql);
		$source_array =Drug_Source::getAllHydrate();
		$all_other_ccc_stores = CCC_store_service_point::getAllBut($stock_type);
		
		$dyn_table = "<table border='1' class='dataTables' cellpadding='5'>";
		$dyn_table .= "<thead>
						<tr><th>Drug Name</th>
					";
		$source_array =array_merge($all_other_ccc_stores,$source_array);
		//echo json_encode($dest_array);die();
		foreach ($source_array as $value) {
			$dyn_table .= "<th>" . $value['Name'] . "</th>";
		}
		$dyn_table .= "</tr>
						</thead>
						<tbody>";

		$results = $query -> result_array();
		if ($results) {
			foreach ($results as $result) {
				$dyn_table .= "<tr><td>" . $result['drug'] . "</td>";
				//Get all destinations for that drug
			    $get_drugs = "
			    			SELECT table1.name,table1.total FROM
			    			(
								(
								SELECT csp.name as name,temp.total 
					              FROM ccc_store_service_point csp
					              LEFT JOIN 
					              (SELECT source_destination,SUM(dsm.quantity) as total 
					              	         FROM drug_stock_movement dsm 
					              	         LEFT JOIN transaction_type t ON t.id=dsm.transaction_type 
					              	         LEFT JOIN drugcode d ON d.id=dsm.drug 
					              	         LEFT JOIN drug_unit du ON du.id=d.unit 
					              	         WHERE dsm.transaction_date 
					              	         BETWEEN '$start_date' 
					              	         AND '$end_date' 
					              	         AND $facilty_value 
					              	         AND t.name LIKE '%received%' 
					              	         AND dsm.drug='" . $result['id'] . "' 
					              	         GROUP BY source_destination) as temp ON temp.source_destination = csp.name
					              	         
					              WHERE csp.id !=$stock_type AND csp.active = 1 
								)
								UNION ALL
								(
								SELECT des.name as name,temp.total 
					              FROM drug_source des  
					              LEFT JOIN (SELECT source_destination,SUM(dsm.quantity) as total 
					              	         FROM drug_stock_movement dsm 
					              	         LEFT JOIN transaction_type t ON t.id=dsm.transaction_type 
					              	         LEFT JOIN drugcode d ON d.id=dsm.drug 
					              	         LEFT JOIN drug_unit du ON du.id=d.unit 
					              	         WHERE dsm.transaction_date 
					              	         BETWEEN '$start_date' 
					              	         AND '$end_date' 
					              	         AND $facilty_value 
					              	         AND t.name LIKE '%received%' 
					              	         AND dsm.drug='" . $result['id'] . "' 
					              	         GROUP BY source_destination) as temp ON temp.source_destination=des.id 
					              	         WHERE des.active=1
	                                         ORDER BY des.id ASC
								)
							) as table1
			    			";        
			    //echo $get_drugs;die();               
				$get_dest = $this -> db -> query($get_drugs);
				$get_des_array = $get_dest -> result_array();
				if($get_des_array){
					foreach ($get_des_array as $value) {
						$total = $value['total'];
						if ($value['total'] == null) {
							$total = 0;
						}
						$dyn_table .= "<td>" . $total . "</td>";
					}
				}
				$dyn_table .= "</tr>";
			}
		} else {
			//$dyn_table .= "<tr><td colspan='4'>No Data Available</td></tr>";
		}
		$dyn_table .= "</tbody></table>";
		$data['dyn_table'] = $dyn_table;
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "drug_inventory_report_row";
		$data['selected_report_type'] = "Stock Consumption";
		$data['report_title'] = "Stock Consumption";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/drugreceived_v';
		$this -> load -> view('template', $data);

	}

	public function getDailyConsumption($start_date = "", $end_date = "") {
		$data['from'] = $start_date;
		$data['to'] = $end_date;
		$start_date = date('Y-m-d', strtotime($start_date));
		$end_date = date('Y-m-d', strtotime($end_date));
		$facility_code = $this -> session -> userdata('facility');
		$consumption_totals = array();
		$row_string = "";
		$drug_total = 0;
		$total = 0;
		$overall_pharmacy_drug_qty = 0;
		$overall_store_drug_qty = 0;
		$pharmacy_drug_qty_percentage = "";
		$store_drug_qty_percentage = "";
		$drug_total_percentage = "";
		$total_drug_qty = 0;

		//Select total consumption at facility
		$sql = "select sum(dsm.quantity_out) as total from drug_stock_movement dsm  where dsm.transaction_date between '$start_date' and '$end_date' and dsm.facility='$facility_code'";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		if ($results) {
			$total = $results[0]['total'];
		}

		//Select total consumption at facility per drug per day
		$sql = "select dsm.transaction_date,dsm.drug,d.drug as Name,d.pack_size,du.Name as unit,sum(dsm.quantity_out) as qty from drug_stock_movement dsm left join drugcode d on dsm.drug=d.id left join drug_unit du on d.unit=du.id where dsm.transaction_date between '$start_date' and '$end_date' and dsm.facility='$facility_code' GROUP BY dsm.transaction_date, dsm.drug ORDER BY dsm.transaction_date";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		$row_string .= "<table border='1' class='dataTables' cellpadding='5'>
			<thead>
			<tr>
			    <th >Date</th>
				<th >Drug</th>
				<th >Unit</th>
				<th >PackSize</th>
				<th >Total(units)</th>
				<th >%</th>
				<th >Pharmacy(units)</th>
				<th >%</th>
				<th > Store(units)</th>
				<th >%</th>
			</tr>
			</thead>
			<tbody>";
		if ($results) {
			foreach ($results as $result) {
				$consumption_totals[$result['drug']] = $result['qty'];
				$trans_date = $result['transaction_date'];
				$current_date = date('d-M-Y', strtotime($result['transaction_date']));
				$current_drug = $result['drug'];
				$current_drugname = $result['Name'];
				$unit = $result['unit'];
				$drug_total = 0;
				$pack_size = $result['pack_size'];
				$drug_total = $result['qty'];
				$drug_total_percentage = number_format(($drug_total / $total) * 100, 1);
				$row_string .= "<tr><td>$current_date</td><td><b>$current_drugname</b></td><td><b>$unit</b></td><td><b>$pack_size</b></td><td>" . number_format($drug_total) . "</td><td>$drug_total_percentage</td>";
				//Select consumption at pharmacy
				$sql = "select transaction_date,drug,sum(quantity_out) as qty from drug_stock_movement where transaction_date= '$trans_date'  and facility='$facility_code' and source='$facility_code' and source=destination and drug='$current_drug' GROUP BY drug ORDER BY transaction_date";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				if ($results) {
					foreach ($results as $result) {
						$total_pharmacy_drug_qty = $result['qty'];
						$overall_pharmacy_drug_qty += $total_pharmacy_drug_qty;
						if ($drug_total > 0) {
							$pharmacy_drug_qty_percentage = number_format(($total_pharmacy_drug_qty / $drug_total) * 100, 1);
						} else {
							$pharmacy_drug_qty_percentage = "-";
						}
						if ($result['drug'] != null) {
							$row_string .= "<td>" . number_format($total_pharmacy_drug_qty) . "</td><td>$pharmacy_drug_qty_percentage</td>";
						}
					}
				} else {
					$row_string .= "<td>-</td><td>-</td>";
				}
				//Select Consumption at store
				$sql = "select transaction_date,drug,sum(quantity_out) as qty from drug_stock_movement where transaction_date= '$trans_date' and facility='$facility_code' and destination='' and source='$facility_code' and drug='$current_drug' GROUP BY drug ORDER BY transaction_date";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				if ($results) {
					foreach ($results as $result) {
						$total_store_drug_qty = $result['qty'];
						$overall_store_drug_qty += $total_store_drug_qty;
						if ($drug_total > 0) {
							$store_drug_qty_percentage = number_format(($total_store_drug_qty / $drug_total) * 100, 1);
						} else {
							$store_drug_qty_percentage = "-";
						}
						if ($result['drug'] != null) {
							$row_string .= "<td>" . number_format($total_store_drug_qty) . "</td><td>$store_drug_qty_percentage</td>";
						}
					}
				} else {
					$row_string .= "<td>-</td><td>-</td>";
				}
				$row_string .= "</tr>";
			}
			$my_total = $overall_pharmacy_drug_qty + $overall_store_drug_qty;
			$row_string .= "</tbody><tfoot><tr><td><b>Totals(units):</b></td><td></td><td></td><td></td><td><b>" . number_format($total) . "</b></td><td><b>100</b></td><td><b>" . number_format($overall_pharmacy_drug_qty) . "</b></td><td><b>" . number_format(($overall_pharmacy_drug_qty / $total) * 100, 1) . "</b></td><td><b>" . number_format($overall_store_drug_qty) . "</b></td><td><b>" . number_format(($overall_store_drug_qty / $total) * 100, 1) . "</b></td></tr>";

		} else {
			//$row_string .= "<tr><td colspan='11'>No Data Available</td></tr>";
		}
		$row_string .= "</tfoot></table>";
		$data['dyn_table'] = $row_string;
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "drug_inventory_report_row";
		$data['selected_report_type'] = "Stock Consumption";
		$data['report_title'] = "Stock Consumption";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/daily_consumption_v';
		$this -> load -> view('template', $data);
	}

	public function getBMI($start_date = "") {
		/*
		 Formula BMI= weight(kg)/(height(m)*height(m))

		 Stages of Obesity
		 --------------------
		 * Very Severely Underweight <15.0
		 * Severely Underweight 15.0-16
		 * Underweight 16.0-18.5
		 * Normal 18.5-25.0
		 * Overweight 25.0-30.0
		 * Obese Class 1(Moderately Obese) 30.0-35.0
		 * Obese Class 2(Severely Obese) 35.0-40.0
		 * Obese Class 3(Very Severely Obese) >40.0
		 */
		$data['from'] = $start_date;
		$start_date = date('Y-m-d', strtotime($start_date));
		$facility_code = $this -> session -> userdata('facility');
		$bmi_temp = array();

		$sql = "SELECT gender,rst.Name,ROUND((((weight)*10000)/(height*height)),1) AS BMI 
		        FROM patient p 
		        LEFT JOIN gender g ON g.id=p.gender 
		        LEFT JOIN regimen_service_type rst ON rst.id=p.service 
		        LEFT JOIN patient_status ps ON ps.id=p.current_status 
		        WHERE p.date_enrolled<='$start_date' 
		        AND p.facility_code='$facility_code' 
		        AND ps.Name LIKE '%active%' 
		        GROUP BY patient_number_ccc";
				$query = $this -> db -> query($sql);
		        $results = $query -> result_array();


		$bmi_temp['ART']['Very Severely Underweight']['Male'] = 0;
		$bmi_temp['ART']['Severely Underweight']['Male'] = 0;
		$bmi_temp['ART']['Underweight']['Male'] = 0;
		$bmi_temp['ART']['Normal']['Male'] = 0;
		$bmi_temp['ART']['Overweight']['Male'] = 0;
		$bmi_temp['ART']['Moderately Obese']['Male'] = 0;
		$bmi_temp['ART']['Severely Obese']['Male'] = 0;
		$bmi_temp['ART']['Very Severely Obese']['Male'] = 0;

		$bmi_temp['ART']['Very Severely Underweight']['Female'] = 0;
		$bmi_temp['ART']['Severely Underweight']['Female'] = 0;
		$bmi_temp['ART']['Underweight']['Female'] = 0;
		$bmi_temp['ART']['Normal']['Female'] = 0;
		$bmi_temp['ART']['Overweight']['Female'] = 0;
		$bmi_temp['ART']['Moderately Obese']['Female'] = 0;
		$bmi_temp['ART']['Severely Obese']['Female'] = 0;
		$bmi_temp['ART']['Very Severely Obese']['Female'] = 0;

		$bmi_temp['PEP']['Very Severely Underweight']['Male'] = 0;
		$bmi_temp['PEP']['Severely Underweight']['Male'] = 0;
		$bmi_temp['PEP']['Underweight']['Male'] = 0;
		$bmi_temp['PEP']['Normal']['Male'] = 0;
		$bmi_temp['PEP']['Overweight']['Male'] = 0;
		$bmi_temp['PEP']['Moderately Obese']['Male'] = 0;
		$bmi_temp['PEP']['Severely Obese']['Male'] = 0;
		$bmi_temp['PEP']['Very Severely Obese']['Male'] = 0;

		$bmi_temp['PEP']['Very Severely Underweight']['Female'] = 0;
		$bmi_temp['PEP']['Severely Underweight']['Female'] = 0;
		$bmi_temp['PEP']['Underweight']['Female'] = 0;
		$bmi_temp['PEP']['Normal']['Female'] = 0;
		$bmi_temp['PEP']['Overweight']['Female'] = 0;
		$bmi_temp['PEP']['Moderately Obese']['Female'] = 0;
		$bmi_temp['PEP']['Severely Obese']['Female'] = 0;
		$bmi_temp['PEP']['Very Severely Obese']['Female'] = 0;

		$bmi_temp['PMTCT']['Very Severely Underweight']['Male'] = 0;
		$bmi_temp['PMTCT']['Severely Underweight']['Male'] = 0;
		$bmi_temp['PMTCT']['Underweight']['Male'] = 0;
		$bmi_temp['PMTCT']['Normal']['Male'] = 0;
		$bmi_temp['PMTCT']['Overweight']['Male'] = 0;
		$bmi_temp['PMTCT']['Moderately Obese']['Male'] = 0;
		$bmi_temp['PMTCT']['Severely Obese']['Male'] = 0;
		$bmi_temp['PMTCT']['Very Severely Obese']['Male'] = 0;

		$bmi_temp['PMTCT']['Very Severely Underweight']['Female'] = 0;
		$bmi_temp['PMTCT']['Severely Underweight']['Female'] = 0;
		$bmi_temp['PMTCT']['Underweight']['Female'] = 0;
		$bmi_temp['PMTCT']['Normal']['Female'] = 0;
		$bmi_temp['PMTCT']['Overweight']['Female'] = 0;
		$bmi_temp['PMTCT']['Moderately Obese']['Female'] = 0;
		$bmi_temp['PMTCT']['Severely Obese']['Female'] = 0;
		$bmi_temp['PMTCT']['Very Severely Obese']['Female'] = 0;

		$bmi_temp['OI']['Very Severely Underweight']['Male'] = 0;
		$bmi_temp['OI']['Severely Underweight']['Male'] = 0;
		$bmi_temp['OI']['Underweight']['Male'] = 0;
		$bmi_temp['OI']['Normal']['Male'] = 0;
		$bmi_temp['OI']['Overweight']['Male'] = 0;
		$bmi_temp['OI']['Moderately Obese']['Male'] = 0;
		$bmi_temp['OI']['Severely Obese']['Male'] = 0;
		$bmi_temp['OI']['Very Severely Obese']['Male'] = 0;

		$bmi_temp['OI']['Very Severely Underweight']['Female'] = 0;
		$bmi_temp['OI']['Severely Underweight']['Female'] = 0;
		$bmi_temp['OI']['Underweight']['Female'] = 0;
		$bmi_temp['OI']['Normal']['Female'] = 0;
		$bmi_temp['OI']['Overweight']['Female'] = 0;
		$bmi_temp['OI']['Moderately Obese']['Female'] = 0;
		$bmi_temp['OI']['Severely Obese']['Female'] = 0;
		$bmi_temp['OI']['Very Severely Obese']['Female'] = 0;

		$male_Very_Severely_Underweight = 0;
		$female_Very_Severely_Underweight = 0;
		$male_Severely_Underweight = 0;
		$female_Severely_Underweight = 0;
		$male_Underweight = 0;
		$female_Underweight = 0;
		$male_Normal = 0;
		$female_Normal = 0;
		$male_Overweight = 0;
		$female_Overweight = 0;
		$male_Moderately_Obese = 0;
		$female_Moderately_Obese = 0;
		$male_Severely_Obese = 0;
		$female_Severely_Obese = 0;
		$male_Very_Severely_Obese = 0;
		$female_Very_Severely_Obese = 0;

		if ($results) {
			foreach ($results as $result) {
				$temp_string = strtoupper($result['Name']);
				if($temp_string !=""){
					//Check if ART
					$art_check = strpos(strtoupper("art"), $temp_string);
					//Check if PEP
					$pep_check = strpos(strtoupper("pep"), $temp_string);
					//Check if PMTCT
					$pmtct_check = strpos(strtoupper("pmtct"), $temp_string);
					//Check if OI
					$oi_check = strpos(strtoupper("oi only"), $temp_string);
			   

				if ($art_check !== false) {
					if ($result['gender'] == 1) {
						if ($result['BMI'] >= 0 && $result['BMI'] < 15) {
							$bmi_temp['ART']['Very Severely Underweight']['Male']++;
							$male_Very_Severely_Underweight++;
						} else if ($result['BMI'] >= 15 && $result['BMI'] < 16) {
							$bmi_temp['ART']['Severely Underweight']['Male']++;
							$male_Severely_Underweight++;
						} else if ($result['BMI'] >= 16 && $result['BMI'] < 18.5) {
							$bmi_temp['ART']['Underweight']['Male']++;
							$male_Underweight++;
						} else if ($result['BMI'] >= 18.5 && $result['BMI'] < 25) {
							$bmi_temp['ART']['Normal']['Male']++;
							$male_Normal++;
						} else if ($result['BMI'] >= 25 && $result['BMI'] < 30) {
							$bmi_temp['ART']['Overweight']['Male']++;
							$male_Overweight++;
						} else if ($result['BMI'] >= 30 && $result['BMI'] < 35) {
							$bmi_temp['ART']['Moderately Obese']['Male']++;
							$male_Moderately_Obese++;
						} else if ($result['BMI'] >= 35 && $result['BMI'] < 40) {
							$bmi_temp['ART']['Severely Obese']['Male']++;
							$male_Severely_Obese++;
						} else if ($result['BMI'] >= 40) {
							$bmi_temp['ART']['Very Severely Obese']['Male']++;
							$male_Very_Severely_Obese++;
						}

					} else if ($result['gender'] == 2) {
						if ($result['BMI'] >= 0 && $result['BMI'] < 15) {
							$bmi_temp['ART']['Very Severely Underweight']['Female']++;
							$female_Very_Severely_Underweight++;
						} else if ($result['BMI'] >= 15 && $result['BMI'] < 16) {
							$bmi_temp['ART']['Severely Underweight']['Female']++;
							$female_Severely_Underweight++;
						} else if ($result['BMI'] >= 16 && $result['BMI'] < 18.5) {
							$bmi_temp['ART']['Underweight']['Female']++;
							$female_Underweight++;
						} else if ($result['BMI'] >= 18.5 && $result['BMI'] < 25) {
							$bmi_temp['ART']['Normal']['Female']++;
							$female_Normal++;
						} else if ($result['BMI'] >= 25 && $result['BMI'] < 30) {
							$bmi_temp['ART']['Overweight']['Female']++;
							$female_Overweight++;
						} else if ($result['BMI'] >= 30 && $result['BMI'] < 35) {
							$bmi_temp['ART']['Moderately Obese']['Female']++;
							$female_Moderately_Obese++;
						} else if ($result['BMI'] >= 35 && $result['BMI'] < 40) {
							$bmi_temp['ART']['Severely Obese']['Female']++;
							$female_Severely_Obese++;
						} else if ($result['BMI'] >= 40) {
							$bmi_temp['ART']['Very Severely Obese']['Female']++;
							$female_Very_Severely_Obese++;
						}
					}
				} else if ($pep_check !== false) {
					if ($result['gender'] == 1) {
						if ($result['BMI'] >= 0 && $result['BMI'] < 15) {
							$bmi_temp['PEP']['Very Severely Underweight']['Male']++;
							$male_Very_Severely_Underweight++;
						} else if ($result['BMI'] >= 15 && $result['BMI'] < 16) {
							$bmi_temp['PEP']['Severely Underweight']['Male']++;
							$male_Severely_Underweight++;
						} else if ($result['BMI'] >= 16 && $result['BMI'] < 18.5) {
							$bmi_temp['PEP']['Underweight']['Male']++;
							$male_Underweight++;
						} else if ($result['BMI'] >= 18.5 && $result['BMI'] < 25) {
							$bmi_temp['PEP']['Normal']['Male']++;
							$male_Normal++;
						} else if ($result['BMI'] >= 25 && $result['BMI'] < 30) {
							$bmi_temp['PEP']['Overweight']['Male']++;
							$male_Overweight++;
						} else if ($result['BMI'] >= 30 && $result['BMI'] < 35) {
							$bmi_temp['PEP']['Moderately Obese']['Male']++;
							$male_Moderately_Obese++;
						} else if ($result['BMI'] >= 35 && $result['BMI'] < 40) {
							$bmi_temp['PEP']['Severely Obese']['Male']++;
							$male_Severely_Obese++;
						} else if ($result['BMI'] >= 40) {
							$bmi_temp['PEP']['Very Severely Obese']['Male']++;
							$male_Very_Severely_Obese++;
						}
					} else if ($result['gender'] == 2) {
						if ($result['BMI'] >= 0 && $result['BMI'] < 15) {
							$bmi_temp['PEP']['Very Severely Underweight']['Female']++;
							$female_Very_Severely_Underweight++;
						} else if ($result['BMI'] >= 15 && $result['BMI'] < 16) {
							$bmi_temp['PEP']['Severely Underweight']['Female']++;
							$female_Severely_Underweight++;
						} else if ($result['BMI'] >= 16 && $result['BMI'] < 18.5) {
							$bmi_temp['PEP']['Underweight']['Female']++;
							$female_Underweight++;
						} else if ($result['BMI'] >= 18.5 && $result['BMI'] < 25) {
							$bmi_temp['PEP']['Normal']['Female']++;
							$female_Normal++;
						} else if ($result['BMI'] >= 25 && $result['BMI'] < 30) {
							$bmi_temp['PEP']['Overweight']['Female']++;
							$female_Overweight++;
						} else if ($result['BMI'] >= 30 && $result['BMI'] < 35) {
							$bmi_temp['PEP']['Moderately Obese']['Female']++;
							$female_Moderately_Obese++;
						} else if ($result['BMI'] >= 35 && $result['BMI'] < 40) {
							$bmi_temp['PEP']['Severely Obese']['Female']++;
							$female_Severely_Obese++;
						} else if ($result['BMI'] >= 40) {
							$bmi_temp['PEP']['Very Severely Obese']['Female']++;
							$female_Very_Severely_Obese++;
						}
					}
				} else if ($pmtct_check !== false) {
					if ($result['gender'] == 1) {
						if ($result['BMI'] >= 0 && $result['BMI'] < 15) {
							$bmi_temp['PMTCT']['Very Severely Underweight']['Male']++;
							$male_Very_Severely_Underweight++;
						} else if ($result['BMI'] >= 15 && $result['BMI'] < 16) {
							$bmi_temp['PMTCT']['Severely Underweight']['Male']++;
							$male_Severely_Underweight++;
						} else if ($result['BMI'] >= 16 && $result['BMI'] < 18.5) {
							$bmi_temp['PMTCT']['Underweight']['Male']++;
							$male_Underweight++;
						} else if ($result['BMI'] >= 18.5 && $result['BMI'] < 25) {
							$bmi_temp['PMTCT']['Normal']['Male']++;
							$male_Normal++;
						} else if ($result['BMI'] >= 25 && $result['BMI'] < 30) {
							$bmi_temp['PMTCT']['Overweight']['Male']++;
							$male_Overweight++;
						} else if ($result['BMI'] >= 30 && $result['BMI'] < 35) {
							$bmi_temp['PMTCT']['Moderately Obese']['Male']++;
							$male_Moderately_Obese++;
						} else if ($result['BMI'] >= 35 && $result['BMI'] < 40) {
							$bmi_temp['PMTCT']['Severely Obese']['Male']++;
							$male_Severely_Obese++;
						} else if ($result['BMI'] >= 40) {
							$bmi_temp['PMTCT']['Very Severely Obese']['Male']++;
							$male_Very_Severely_Obese++;
						}
					} else if ($result['gender'] == 2) {
						if ($result['BMI'] >= 0 && $result['BMI'] < 15) {
							$bmi_temp['PMTCT']['Very Severely Underweight']['Female']++;
							$female_Very_Severely_Underweight++;
						} else if ($result['BMI'] >= 15 && $result['BMI'] < 16) {
							$bmi_temp['PMTCT']['Severely Underweight']['Female']++;
							$female_Severely_Underweight++;
						} else if ($result['BMI'] >= 16 && $result['BMI'] < 18.5) {
							$bmi_temp['PMTCT']['Underweight']['Female']++;
							$female_Underweight++;
						} else if ($result['BMI'] >= 18.5 && $result['BMI'] < 25) {
							$bmi_temp['PMTCT']['Normal']['Female']++;
							$female_Normal++;
						} else if ($result['BMI'] >= 25 && $result['BMI'] < 30) {
							$bmi_temp['PMTCT']['Overweight']['Female']++;
							$female_Overweight++;
						} else if ($result['BMI'] >= 30 && $result['BMI'] < 35) {
							$bmi_temp['PMTCT']['Moderately Obese']['Female']++;
							$female_Moderately_Obese++;
						} else if ($result['BMI'] >= 35 && $result['BMI'] < 40) {
							$bmi_temp['PMTCT']['Severely Obese']['Female']++;
							$female_Severely_Obese++;
						} else if ($result['BMI'] >= 40) {
							$bmi_temp['PMTCT']['Very Severely Obese']['Female']++;
							$female_Very_Severely_Obese++;
						}
					}
				} else if ($oi_check !== false) {
					if ($result['gender'] == 1) {
						if ($result['BMI'] >= 0 && $result['BMI'] < 15) {
							$bmi_temp['OI']['Very Severely Underweight']['Male']++;
							$male_Very_Severely_Underweight++;
						} else if ($result['BMI'] >= 15 && $result['BMI'] < 16) {
							$bmi_temp['OI']['Severely Underweight']['Male']++;
							$male_Severely_Underweight++;
						} else if ($result['BMI'] >= 16 && $result['BMI'] < 18.5) {
							$bmi_temp['OI']['Underweight']['Male']++;
							$male_Underweight++;
						} else if ($result['BMI'] >= 18.5 && $result['BMI'] < 25) {
							$bmi_temp['OI']['Normal']['Male']++;
							$male_Normal++;
						} else if ($result['BMI'] >= 25 && $result['BMI'] < 30) {
							$bmi_temp['OI']['Overweight']['Male']++;
							$male_Overweight++;
						} else if ($result['BMI'] >= 30 && $result['BMI'] < 35) {
							$bmi_temp['OI']['Moderately Obese']['Male']++;
							$male_Moderately_Obese++;
						} else if ($result['BMI'] >= 35 && $result['BMI'] < 40) {
							$bmi_temp['OI']['Severely Obese']['Male']++;
							$male_Severely_Obese++;
						} else if ($result['BMI'] >= 40) {
							$bmi_temp['OI']['Very Severely Obese']['Male']++;
							$male_Very_Severely_Obese++;
						}
					} else if ($result['gender'] == 2) {
						if ($result['BMI'] >= 0 && $result['BMI'] < 15) {
							$bmi_temp['OI']['Very Severely Underweight']['Female']++;
							$female_Very_Severely_Underweight++;
						} else if ($result['BMI'] >= 15 && $result['BMI'] < 16) {
							$bmi_temp['OI']['Severely Underweight']['Female']++;
							$female_Severely_Underweight++;
						} else if ($result['BMI'] >= 16 && $result['BMI'] < 18.5) {
							$bmi_temp['OI']['Underweight']['Female']++;
							$female_Underweight++;
						} else if ($result['BMI'] >= 18.5 && $result['BMI'] < 25) {
							$bmi_temp['OI']['Normal']['Female']++;
							$female_Normal++;
						} else if ($result['BMI'] >= 25 && $result['BMI'] < 30) {
							$bmi_temp['OI']['Overweight']['Female']++;
							$female_Overweight++;
						} else if ($result['BMI'] >= 30 && $result['BMI'] < 35) {
							$bmi_temp['OI']['Moderately Obese']['Female']++;
							$female_Moderately_Obese++;
						} else if ($result['BMI'] >= 35 && $result['BMI'] < 40) {
							$bmi_temp['OI']['Severely Obese']['Female']++;
							$female_Severely_Obese++;
						} else if ($result['BMI'] >= 40) {
							$bmi_temp['OI']['Very Severely Obese']['Female']++;
							$female_Very_Severely_Obese++;
						}
					}
				  }
				}
			}
		}
		$dyn_table = "<table border='1' cellpadding='5' class='dataTables'><thead>";
		$dyn_table .= "<tr><th></th><th>Very Severely Underweight</th><th></th><th>Severely Underweight</th><th></th><th>Underweight</th><th></th><th>Normal</th><th></th><th>Overweight</th><th></th><th>Moderately Obese</th><th></th><th>Severely Obese</th><th></th><th>Very Severely Obese</th><th></th></tr>";
		$dyn_table .= "<tr><th>Type of Service</th><th>Male</th><th>Female</th><th>Male</th><th>Female</th><th>Male</th><th>Female</th><th>Male</th><th>Female</th><th>Male</th><th>Female</th><th>Male</th><th>Female</th><th>Male</th><th>Female</th><th>Male</th><th>Female</th></tr><tbody>";
		foreach ($bmi_temp as $temp_values => $temp_value) {
			$dyn_table .= "<tr><td>$temp_values</td>";
			foreach ($temp_value as $temp_data => $temp_code) {
				foreach ($temp_code as $code) {
					$dyn_table .= "<td>$code</td>";
				}
			}
			$dyn_table .= "</tr>";
		}
		$dyn_table .= "</tbody><tfoot><tr class='tfoot'><td><b>TOTALS</b></td><td><b>" . number_format($male_Very_Severely_Underweight) . "</b></td><td><b>" . number_format($female_Very_Severely_Underweight) . "</b></td><td><b>" . number_format($male_Severely_Underweight) . "</b></td><td><b>" . number_format($female_Severely_Underweight) . "</b></td><td><b>" . number_format($male_Underweight) . "</b></td><td><b>" . number_format($female_Underweight) . "</b></td><td><b>" . number_format($male_Normal) . "</b></td><td><b>" . number_format($female_Normal) . "</b></td><td><b>" . number_format($male_Overweight) . "</b></td><td><b>" . number_format($female_Overweight) . "</b></td><td><b>" . number_format($male_Moderately_Obese) . "</b></td><td><b>" . number_format($female_Moderately_Obese) . "</b></td><td><b>" . number_format($male_Severely_Obese) . "</b></td><td><b>" . number_format($female_Severely_Obese) . "</b></td><td><b>" . number_format($male_Very_Severely_Obese) . "</b></td><td><b>" . number_format($female_Very_Severely_Obese) . "</b></td></tr>";
		$dyn_table .= "</tfoot></table>";

		$data['overall'] = $male_Very_Severely_Underweight + $female_Very_Severely_Underweight + $male_Severely_Underweight + $female_Severely_Underweight + $male_Underweight + $female_Underweight + $male_Normal + $female_Normal + $male_Overweight + $female_Overweight + $male_Moderately_Obese + $female_Moderately_Obese + $male_Severely_Obese + $female_Severely_Obese + $male_Very_Severely_Obese + $female_Very_Severely_Obese;
		$data['dyn_table'] = $dyn_table;
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "standard_report_select";
		$data['selected_report_type'] = "Standard Reports";
		$data['report_title'] = "Patient BMI Summary";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/patient_bmi_v';
		$this -> load -> view('template', $data);
		//End
	}

	public function service_statistics($start_date = "") {
		//Variables
		$facility_code = $this -> session -> userdata("facility");
		$data['from'] = $start_date;
		$from = date('Y-m-d', strtotime($start_date));
		$regimen_totals = array();
		$data = array();
		$total = 0;
		$overall_adult_male_art = 0;
		$overall_adult_male_pep = 0;
		$overall_adult_male_oi = 0;

		$overall_adult_female_art = 0;
		$overall_adult_female_pep = 0;
		$overall_adult_female_pmtct = 0;
		$overall_adult_female_oi = 0;

		$overall_child_male_art = 0;
		$overall_child_male_pep = 0;
		$overall_child_male_pmtct = 0;
		$overall_child_male_oi = 0;

		$overall_child_female_art = 0;
		$overall_child_female_pep = 0;
		$overall_child_female_pmtct = 0;
		$overall_child_female_oi = 0;

		//Get Total of all patients
		$sql = "SELECT p.current_regimen,count(*) as total FROM patient p 
				LEFT JOIN regimen r ON r.id = p.current_regimen 
				LEFT JOIN regimen_service_type rst ON rst.id = p.service 
				LEFT JOIN patient_status ps ON ps.id = p.current_status
				WHERE p.date_enrolled <='$from' AND ps.name ='active' AND p.facility_code = '$facility_code' 
				AND p.current_regimen != '' AND p.current_status != ''";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();
		$total = $results[0]['total'];

		//Get Totals for each regimen
		$sql = "SELECT count(*) as total, r.regimen_desc,r.regimen_code,p.current_regimen FROM patient p 
				LEFT JOIN regimen r ON r.id = p.current_regimen LEFT JOIN regimen_service_type rst ON rst.id = p.service 
				LEFT JOIN patient_status ps ON ps.id = p.current_status
				WHERE p.date_enrolled <='$from' AND ps.name ='active' AND p.facility_code = '$facility_code' 
				AND p.current_regimen != '' AND p.current_status != '' GROUP BY p.current_regimen ORDER BY r.regimen_code ASC";
		$query = $this -> db -> query($sql);
		$results = $query -> result_array();

		if ($results) {
			$dyn_table = "<table id='patient_listingh' border='1' cellpadding='5' class='dataTables'><thead>
			<tr>
				<th ></th>
				<th>Total</th><th></th>
				<th> Adult</th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th>
				<th> Children </th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th>
			</tr>
			<tr>
				<th></th>
				<th></th>
				<th></th>
				<th>Male</th><th></th><th></th><th></th><th></th><th></th>
				<th>Female</th><th></th><th></th><th></th><th></th><th></th><th></th><th></th>
				<th>Male</th><th></th><th></th><th></th><th></th><th></th><th></th><th></th>
				<th>Female</th><th></th><th></th><th></th><th></th><th></th><th></th><th></th>
			</tr>
			<tr>
				<th></th>
				<th></th>
				<th></th>
				<th>ART</th><th></th>
				<th>PEP</th><th></th>
				<th>OI</th><th></th>
				<th>ART</th><th></th>
				<th>PEP</th><th></th>
				<th>PMTCT</th><th></th>
				<th>OI</th><th></th>
				<th>ART</th><th></th>
				<th>PEP</th><th></th>
				<th>PMTCT</th><th></th>
				<th>OI</th><th></th>
				<th>ART</th><th></th>
				<th>PEP</th><th></th>
				<th>PMTCT</th><th></th>
				<th>OI</th><th></th>
			</tr>
			<tr>
				<th>Regimen</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
				<th>No.</th>
				<th>%</th>
			</tr>
			</thead>
			<tbody>";
			foreach ($results as $result) {
				$regimen_totals[$result['current_regimen']] = $result['total'];
				$current_regimen = $result['current_regimen'];
				$regimen_name = $result['regimen_desc'];
				$regimen_code = $result['regimen_code'];
				$regimen_total = $result['total'];
				$regimen_total_percentage = number_format(($regimen_total / $total) * 100, 1);
				$dyn_table .= "<tr><td><b>$regimen_code</b> | $regimen_name</td><td>$regimen_total</td><td>$regimen_total_percentage</td>";

				//SQL for Adult Male Regimens
				$sql = "SELECT count(*) as total,p.service as service_id,rst.name FROM patient p 
						LEFT JOIN regimen r ON r.id = p.current_regimen LEFT JOIN regimen_service_type rst ON rst.id = p.service 
						LEFT JOIN patient_status ps ON ps.id = p.current_status
						WHERE p.date_enrolled <='$from' AND ps.name ='active' 
						AND p.facility_code = '$facility_code' AND p.current_regimen != '' 
						AND p.current_status != '' AND p.gender=1 AND p.current_regimen='$current_regimen' AND FLOOR(datediff('$from',p.dob)/365)>15 
						GROUP BY p.service ORDER BY rst.id ASC";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				$total_adult_male_art = "-";
				$total_adult_male_pep = "-";
				$total_adult_male_oi = "-";
				$total_adult_male_art_percentage = "-";
				$total_adult_male_pep_percentage = "-";
				$total_adult_male_oi_percentage = "-";
				if ($results) {
					foreach ($results as $result) {
						$total_adult_male = $result['total'];
						$service_code = $result['service_id'];
						$service_name = $result['name'];
						if ($service_name == "ART") {
							$overall_adult_male_art += $total_adult_male;
							$total_adult_male_art = number_format($total_adult_male);
							$total_adult_male_art_percentage = number_format(($total_adult_male / $total) * 100, 1);
						} else if ($service_name == "PEP") {
							$overall_adult_male_pep += $total_adult_male;
							$total_adult_male_pep = number_format($total_adult_male);
							$total_adult_male_pep_percentage = number_format(($total_adult_male_pep / $total) * 100, 1);
						} else if ($service_name == "OI Only") {
							$overall_adult_male_oi += $total_adult_male;
							$total_adult_male_oi = number_format($total_adult_male);
							$total_adult_male_oi_percentage = number_format(($total_adult_male_oi / $total) * 100, 1);
						}

					}
					$dyn_table .= "<td>$total_adult_male_art</td><td>$total_adult_male_art_percentage</td><td>$total_adult_male_pep</td><td>$total_adult_male_pep_percentage</td><td>$total_adult_male_oi</td><td>$total_adult_male_oi_percentage</td>";
				} else {
					$dyn_table .= "<td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td>";
				}

				//SQL for Adult Female Regimens
				$sql = "SELECT count(*) as total,p.service as service_id,rst.name FROM patient p LEFT JOIN regimen r ON r.id = p.current_regimen LEFT JOIN regimen_service_type rst ON rst.id = p.service WHERE p.date_enrolled <='$from' AND p.current_status =1 AND p.facility_code = '$facility_code' AND p.current_regimen != '' AND p.current_status != '' AND p.gender=2 AND p.current_regimen='$current_regimen' AND FLOOR(datediff('$from',p.dob)/365)>15 GROUP BY p.service ORDER BY rst.id ASC";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				$total_adult_female_art = "-";
				$total_adult_female_pep = "-";
				$total_adult_female_pmtct = "-";
				$total_adult_female_oi = "-";
				$total_adult_female_art_percentage = "-";
				$total_adult_female_pep_percentage = "-";
				$total_adult_female_pmtct_percentage = "-";
				$total_adult_female_oi_percentage = "-";
				if ($results) {
					foreach ($results as $result) {
						$total_adult_female = $result['total'];
						$service_code = $result['service_id'];
						$service_name = $result['name'];
						if ($service_name == "ART") {
							$overall_adult_female_art += $total_adult_female;
							$total_adult_female_art = number_format($total_adult_female);
							$total_adult_female_art_percentage = number_format(($total_adult_female / $total) * 100, 1);
						} else if ($service_name == "PEP") {
							$overall_adult_female_pep += $total_adult_female;
							$total_adult_female_pep = number_format($total_adult_female);
							$total_adult_female_pep_percentage = number_format(($total_adult_female_pep / $total) * 100, 1);
						} else if ($service_name == "PMTCT") {
							$overall_adult_female_pmtct += $total_adult_female;
							$total_adult_female_pmtct = number_format($total_adult_female);
							$total_adult_female_pmtct_percentage = number_format(($total_adult_female_pmtct / $total) * 100, 1);
						} else if ($service_name == "OI Only") {
							$overall_adult_female_oi += $total_adult_female;
							$total_adult_female_oi = number_format($total_adult_female);
							$total_adult_female_oi_percentage = number_format(($total_adult_female_oi / $total) * 100, 1);
						}

					}
					$dyn_table .= "<td>$total_adult_female_art</td><td>$total_adult_female_art_percentage</td><td>$total_adult_female_pep</td><td>$total_adult_female_pep_percentage</td><td>$total_adult_female_pmtct</td><td>$total_adult_female_pmtct_percentage</td><td>$total_adult_female_oi</td><td>$total_adult_female_oi_percentage</td>";
				} else {
					$dyn_table .= "<td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td>";
				}

				//SQL for Child Male Regimens
				$sql = "SELECT count(*) as total,p.service as service_id,rst.name FROM patient p LEFT JOIN regimen r ON r.id = p.current_regimen LEFT JOIN regimen_service_type rst ON rst.id = p.service WHERE p.date_enrolled <='$from' AND p.current_status =1 AND p.facility_code = '$facility_code' AND p.current_regimen != '' AND p.current_status != '' AND p.gender=1 AND p.current_regimen='$current_regimen' AND FLOOR(datediff('$from',p.dob)/365)<=15 GROUP BY p.service ORDER BY rst.id ASC";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				$total_child_male_art = "-";
				$total_child_male_pep = "-";
				$total_child_male_pmtct = "-";
				$total_child_male_oi = "-";

				$total_child_male_art_percentage = "-";
				$total_child_male_pep_percentage = "-";
				$total_child_male_pmtct_percentage = "-";
				$total_child_male_oi_percentage = "-";
				if ($results) {
					foreach ($results as $result) {
						$total_child_male = $result['total'];
						$service_code = $result['service_id'];
						$service_name = $result['name'];
						if ($service_name == "ART") {
							$overall_child_male_art += $total_child_male;
							$total_child_male_art = number_format($total_child_male);
							$total_child_male_art_percentage = number_format(($total_child_male / $total) * 100, 1);
						} else if ($service_name == "PEP") {
							$overall_child_male_pep += $total_child_male;
							$total_child_male_pep = number_format($total_child_male);
							$total_child_male_pep_percentage = number_format(($total_child_male_pep / $total) * 100, 1);
						} else if ($service_name == "PMTCT") {
							$overall_child_male_pmtct += $total_child_male;
							$total_child_male_pmtct = number_format($total_child_male);
							$total_child_male_pmtct_percentage = number_format(($total_child_male_pmtct / $total) * 100, 1);
						} else if ($service_name == "OI Only") {
							$overall_child_male_oi += $total_child_male;
							$total_child_male_oi = number_format($total_child_male);
							$total_child_male_oi_percentage = number_format(($total_child_male_oi / $total) * 100, 1);
						}

					}
					$dyn_table .= "<td>$total_child_male_art</td><td>$total_child_male_art_percentage</td><td>$total_child_male_pep</td><td>$total_child_male_pep_percentage</td><td>$total_child_male_pmtct</td><td>$total_child_male_pmtct_percentage</td><td>$total_child_male_oi</td><td>$total_child_male_oi_percentage</td>";
				} else {
					$dyn_table .= "<td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td>";
				}

				//SQL for Child Female Regimens
				$sql = "SELECT count(*) as total,p.service as service_id,rst.name FROM patient p LEFT JOIN regimen r ON r.id = p.current_regimen LEFT JOIN regimen_service_type rst ON rst.id = p.service WHERE p.date_enrolled <='$from' AND p.current_status =1 AND p.facility_code = '$facility_code' AND p.current_regimen != '' AND p.current_status != '' AND p.gender=2 AND p.current_regimen='$current_regimen' AND FLOOR(datediff('$from',p.dob)/365)<=15 GROUP BY p.service ORDER BY rst.id ASC";
				$query = $this -> db -> query($sql);
				$results = $query -> result_array();
				$total_child_female_art = "-";
				$total_child_female_pep = "-";
				$total_child_female_pmtct = "-";
				$total_child_female_oi = "-";
				$total_child_female_art_percentage = "-";
				$total_child_female_pep_percentage = "-";
				$total_child_female_pmtct_percentage = "-";
				$total_child_female_oi_percentage = "-";
				if ($results) {
					foreach ($results as $result) {
						$total_child_female = $result['total'];
						$service_code = $result['service_id'];
						$service_name = $result['name'];
						if ($service_name == "ART") {
							$overall_child_female_art += $total_child_female;
							$total_child_female_art = number_format($total_child_female);
							$total_child_female_art_percentage = number_format(($total_child_female / $total) * 100, 1);
						} else if ($service_name == "PEP") {
							$overall_child_female_pep += $total_child_female;
							$total_child_female_pep = number_format($total_child_female);
							$total_child_female_pep_percentage = number_format(($total_child_female_pep / $total) * 100, 1);
						} else if ($service_name == "PMTCT") {
							$overall_child_female_pmtct += $total_child_female;
							$total_child_female_pmtct = number_format($total_child_female);
							$total_child_female_pmtct_percentage = number_format(($total_child_female_pmtct / $total) * 100, 1);
						} else if ($service_name == "OI Only") {
							$overall_child_female_oi += $total_child_female;
							$total_child_female_oi = number_format($total_child_female);
							$total_child_female_oi_percentage = number_format(($total_child_female_oi / $total) * 100, 1);
						}

					}
					$dyn_table .= "<td>$total_child_female_art</td><td>$total_child_female_art_percentage</td><td>$total_child_female_pep</td><td>$total_child_female_pep_percentage</td><td>$total_child_female_pmtct</td><td>$total_child_female_pmtct_percentage</td><td>$total_child_female_oi</td><td>$total_child_female_oi_percentage</td>";
				} else {
					$dyn_table .= "<td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td>";
				}
				$dyn_table .= "</tr>";
			}
			$overall_art_male_percent = number_format(($overall_adult_male_art / $total) * 100, 1);
			$overall_pep_male_percent = number_format(($overall_adult_male_pep / $total) * 100, 1);
			$overall_oi_male_percent = number_format(($overall_adult_male_oi / $total) * 100, 1);

			$overall_art_female_percent = number_format(($overall_adult_female_art / $total) * 100, 1);
			$overall_pep_female_percent = number_format(($overall_adult_female_pep / $total) * 100, 1);
			$overall_pmtct_female_percent = number_format(($overall_adult_female_pmtct / $total) * 100, 1);
			$overall_oi_female_percent = number_format(($overall_adult_female_oi / $total) * 100, 1);

			$overall_art_childmale_percent = number_format(($overall_child_male_art / $total) * 100, 1);
			$overall_pep_childmale_percent = number_format(($overall_child_male_pep / $total) * 100, 1);
			$overall_oi_childmale_percent = number_format(($overall_child_male_pmtct / $total) * 100, 1);
			$overall_pmtct_childmale_percent = number_format(($overall_child_male_oi / $total) * 100, 1);

			$overall_art_childfemale_percent = number_format(($overall_child_female_art / $total) * 100, 1);
			$overall_pep_childfemale_percent = number_format(($overall_child_female_pep / $total) * 100, 1);
			$overall_pmtct_childfemale_percent = number_format(($overall_child_female_pmtct / $total) * 100, 1);
			$overall_oi_childfemale_percent = number_format(($overall_child_female_oi / $total) * 100, 1);

			$dyn_table .= "</tbody><tfoot><tr><td>TOTALS</td><td>$total</td><td>100</td><td>$overall_adult_male_art</td><td>$overall_art_male_percent</td><td>$overall_adult_male_pep</td><td>$overall_pep_male_percent</td><td>$overall_adult_male_oi</td><td>$overall_oi_male_percent</td><td>$overall_adult_female_art</td><td>$overall_art_female_percent</td><td>$overall_adult_female_pep</td><td>$overall_pep_female_percent</td><td>$overall_adult_female_pmtct</td><td>$overall_pmtct_female_percent</td><td>$overall_adult_female_oi</td><td>$overall_oi_female_percent</td><td>$overall_child_male_art</td><td>$overall_art_childmale_percent</td><td>$overall_child_male_pep</td><td>$overall_pep_childmale_percent</td><td>$overall_child_male_pmtct</td><td>$overall_pmtct_childmale_percent</td><td>$overall_child_male_oi</td><td>$overall_oi_childmale_percent</td><td>$overall_child_female_art</td><td>$overall_art_childfemale_percent</td><td>$overall_child_female_pep</td><td>$overall_pep_childfemale_percent</td><td>$overall_child_female_pmtct</td><td>$overall_pmtct_childfemale_percent</td><td>$overall_child_female_oi</td><td>$overall_oi_childfemale_percent</td></tr></tfoot></table>";
		} else {
			$dyn_table = "<h4 style='text-align: center'><span >No Data Available</span></h4>";
		}
		$data['from'] = date('d-M-Y', strtotime($from));
		$data['dyn_table'] = $dyn_table;
		$data['title'] = "webADT | Reports";
		$data['hide_side_menu'] = 1;
		$data['banner_text'] = "Facility Reports";
		$data['selected_report_type_link'] = "early_warning_report_select";
		$data['selected_report_type'] = "Standard Reports";
		$data['report_title'] = "Active Patients By Regimen ";
		$data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view'] = 'reports/no_of_patients_receiving_art_byregimen_v';
		$this -> load -> view('template', $data);
	}
	
	public function patient_consumption($period_start = "", $period_end=""){
		$patients=array();
		$oi_drugs=array();
        //get all regimen drugs from OI
        $sql="SELECT IF(d.drug IS NULL,rd.drugcode,d.drug) as drugname,'' as drugqty
              FROM regimen_drug rd 
              LEFT JOIN regimen r ON r.id=rd.regimen
              LEFT JOIN regimen_service_type rst ON rst.id=r.type_of_service
              LEFT JOIN drugcode d ON d.id=rd.drugcode
              WHERE rst.name LIKE '%oi%'
              AND d.drug NOT LIKE '%cot%'
              GROUP BY drugname";
        $query=$this->db->query($sql);
	    $drugs=$query->result_array();
	    if($drugs){
	    	foreach($drugs as $drug){
              $oi_drugs[$drug['drugname']]=  $drug['drugqty'] ;
	    	}
	    }

		//get all patients dispensed,drug and in this period
		$sql="SELECT pv.patient_id,CONCAT_WS( '/', MONTH( pv.dispensing_date ) , YEAR( pv.dispensing_date ) ) AS Month_Year, group_concat(d.drug) AS ARVDrug, group_concat(pv.quantity) AS ARVQTY
			  FROM v_patient_visits pv 
			  LEFT JOIN drugcode d ON d.id = pv.drug_id
			  WHERE pv.dispensing_date
			  BETWEEN '".$period_start."'
			  AND '".$period_end."'
			  GROUP BY pv.patient_id, CONCAT_WS( '/', MONTH( pv.dispensing_date ) , YEAR( pv.dispensing_date ) )
			  ORDER BY pv.patient_id";
	    $query=$this->db->query($sql);
	    $transactions=$query->result_array();

	    if($transactions){
	    	foreach($transactions as $transaction){
	          $oi=$oi_drugs;
	          $is_oi=FALSE;
	          //split comma seperated drugs to array
              $drugs=$transaction['ARVDrug'];
              $drugs=explode(",", $drugs);
              //split comma seperated qtys to array
              $qtys=$transaction['ARVQTY'];
              $qtys=explode(",", $qtys);
              foreach($drugs as $index=>$drug){
              	//add drug qtys to oi
              	if(array_key_exists($drug,$oi)){
              		$is_oi=TRUE;
              	    $oi[$drug]=$qtys[$index];
              	}
              }
              //add drug consumption to patient
              if($is_oi==TRUE){
              	$patients[$transaction['patient_id']]=$oi;
              } 
	    	}
	    }

	    //export patient transactions
	    $this -> load -> library('PHPExcel');
		$dir = "Export";
		$objPHPExcel = new PHPExcel();
		$objPHPExcel->setActiveSheetIndex(0);

		/*Delete all files in export folder*/
		if (is_dir($dir)) {
			$files = scandir($dir);
			foreach ($files as $object) {
				if ($object != "." && $object != "..") {
					unlink($dir . "/" . $object);
				}
			}
		} else {
			mkdir($dir);
		}
		//get columns
		$column = array();
		$letter = 'A';
		while ($letter !== 'AAA') {
		    $column[] = $letter++;
		}
        //set col and row indices
		$col=0;
		$row=1;

		//wrap header text
		$objPHPExcel->getActiveSheet()->getStyle('A1:A'.$objPHPExcel->getActiveSheet()->getHighestRow())->getAlignment()->setWrapText(true); 
		
		//autosize header
		$objPHPExcel->getActiveSheet()->getRowDimension('1')->setRowHeight(-1);

		//print 
        $objPHPExcel -> getActiveSheet() -> SetCellValue($column[$col].$row,"ARTID");
        $col++;
        $objPHPExcel -> getActiveSheet() -> SetCellValue($column[$col].$row,"PERIOD");
		foreach($oi_drugs as $drugname=>$header){
			$col++;
            $objPHPExcel -> getActiveSheet() -> SetCellValue($column[$col].$row,$drugname);
		}

		//loop through patient transactions
        foreach ($patients as $art_id=>$dispenses) {
        	//reset col and row indices
			$col=0;
			$row++;
        	//write art_id and period reporting
        	$objPHPExcel -> getActiveSheet() -> SetCellValue($column[$col].$row,$art_id);
        	$col++;
        	$objPHPExcel -> getActiveSheet() -> SetCellValue($column[$col].$row, date("m/Y", strtotime($period_start)));
			foreach ($dispenses as $drug_id => $drug_qty) {
				$col++;
				$objPHPExcel -> getActiveSheet() -> SetCellValue($column[$col].$row, $drug_qty);
			}
		}

		//Generate file
		ob_start();
		$period_start = date("F-Y", strtotime($period_start));
		$original_filename = "PATIENT DRUG CONSUMPTION[" . $period_start . "].xls";
		$filename = $dir . "/" . urldecode($original_filename);
		$objWriter = new PHPExcel_Writer_Excel5($objPHPExcel);
		$objWriter -> save($filename);
		$objPHPExcel -> disconnectWorksheets();
		unset($objPHPExcel);
		if (file_exists($filename)) {
			$filename = str_replace("#", "%23", $filename);
			redirect($filename);
		}
	}

	public function dispensingReport($start_date="",$end_date=""){
		ini_set("max_execution_time", "1000000");
		$filter = "";
		if($start_date!="" && $end_date!=""){
			$start_date = date("Y-m-d",strtotime($start_date));
			$end_date = date("Y-m-d",strtotime($end_date));
			$filter = " WHERE dispensing_date BETWEEN '$start_date' AND '$end_date' ";
		}
		$sql = "SELECT pv.patient_id as 'CCC No', p.first_name as 'First Name', p.last_name as 'Last Name',pv.current_weight as 'Current Weight',pv.dispensing_date as 'Date of Visit',r.regimen_desc as 'Regimen', d.drug as 'Drug Name',pv.quantity as 'Quantity',pv.batch_number as 'Batch Number', IF(b.brand IS NULL,'',b.brand) as 'Brand Name',pv.dose as 'Dose',pv.duration as 'Duration',pv.user as 'Operator'
				FROM patient_visit pv
				LEFT JOIN patient p ON p.patient_number_ccc = pv.patient_id
				LEFT JOIN drugcode d ON d.id = pv.drug_id
				LEFT JOIN brand b ON b.id = pv.brand
				LEFT JOIN regimen r ON r.id = pv.regimen
				$filter
				ORDER BY dispensing_date DESC ";	
		//echo $sql;die();
		$query = $this ->db ->query($sql);
		$result = $query->result_array();
		$counter = 0;
		$table = "<table border='1' cellpadding='2' cellspacing='0' ><thead><tr style='background-color:aliceblue; font-size:16px;font-weight:700'>";
		foreach ($query->list_fields() as $field){
		   $table.="<td>".$field."</td>";
		}
		$table .= "</tr></thead><tbody>";
		foreach ($result as $key => $value) {
			$table.="<tr><td>".$value['CCC No']."</td>
					  <td>".$value['First Name']."</td>
					  <td>".$value['Last Name']."</td>
					  <td>".$value['Current Weight']."</td>
					  <td>".$value['Date of Visit']."</td>
					  <td>".$value['Regimen']."</td>
					  <td>".$value['Drug Name']."</td>
					  <td>".$value['Quantity']."</td>
					  <td>".$value['Batch Number']."</td>
					  <td>".$value['Brand Name']."</td>
					  <td>".$value['Dose']."</td>
					  <td>".$value['Duration']."</td>
					  <td>".$value['Operator']."</td>
					  </tr>";
		}
		$table.= "</tbody></table>";
		$this->mpdf = new mPDF('C', 'A3-L', 0, '', 5, 5, 5, 5, 7, 9, '');
		$this->mpdf->WriteHTML($table);
		$this->mpdf->ignore_invalid_utf8 = true;
		$name = "Dispensing History as of ".date("Y_m_d").".pdf";
		$this ->deleteAllFiles("./assets/download/");//Delete all files in folder first
		write_file("./assets/download/$name", $this->mpdf->Output($name,'D'));
		
	}

	function deleteAllFiles($directory=""){
		if($directory!=""){
			foreach(glob("{$directory}/*") as $file)
		    {
		        if(is_dir($file)) { 
		            deleteAllFiles($file);
		        } else {
		            unlink($file);
		        }
		    }
		}
	}
    //loading guidelines
    public function load_guidelines_view() {
	    $this->load->helper('directory');
	    
	    $dir = realpath($_SERVER['DOCUMENT_ROOT']);
	    $files = directory_map($dir.'/ADT/assets/guidelines/');
	   
	    $columns=array('#','File Name','Action');
	    $tmpl = array('table_open' => '<table class="table table-bordered table-hover table-condensed table-striped dataTables" >');
	    $this -> table -> set_template($tmpl);
	    $this -> table -> set_heading($columns);
	     
	    foreach($files as $file){
	   
	    $links = "<a href='".base_url()."assets/Guidelines/".$file."'target='_blank'>View</a>";
	    
	    
	    $this -> table -> add_row("",$file, $links);    
	    }
	    $data['guidelines_list'] = $this -> table -> generate();
	    $data['hide_side_menu'] = 1;
	    $data['selected_report_type_link'] = "guidelines_report_row";
	    $data['selected_report_type'] = "List of Guidelines";
	    $data['report_title'] = "List of Guidelines";
	    $data['facility_name'] = $this -> session -> userdata('facility_name');
		$data['content_view']='guidelines_listing_v';
	    $this -> base_params($data);
    }
	public function base_params($data) {
		$data['reports'] = true;
		$data['title'] = "webADT | Reports";
		$data['banner_text'] = "Facility Reports";
		$this -> load -> view('template', $data);
	}
	
}

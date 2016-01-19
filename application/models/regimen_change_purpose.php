<?php
class Regimen_Change_Purpose extends Doctrine_Record {

	public function setTableDefinition() {
		$this -> hasColumn('Name', 'varchar', 50);
		$this -> hasColumn('Active', 'varchar', 2);
	}

	public function setUp() {
		$this -> setTableName('regimen_change_purpose');
	}

	public function getAll() {
		$query = Doctrine_Query::create() -> select("*") -> from("Regimen_Change_Purpose") -> where("Active", "1");
		$purposes = $query -> execute();
		return $purposes;
	}
	public function getAllHydrated() {
		$query = Doctrine_Query::create() -> select("*") -> from("Regimen_Change_Purpose") -> where("Active", "1");
		$purposes = $query -> execute(array(), Doctrine::HYDRATE_ARRAY);
		return $purposes;
	}

	public function getTotalNumber() {
		$query = Doctrine_Query::create() -> select("count(*) as Total_Purposes") -> from("Regimen_Change_Purpose");
		$total = $query -> execute();
		return $total[0]['Total_Purposes'];
	}

	public function getPagedPurposes($offset, $items) {
		$query = Doctrine_Query::create() -> select("Name") -> from("Regimen_Change_Purpose") -> offset($offset) -> limit($items);
		$purpose = $query -> execute();
		return $purpose;
	}
	
	public function getThemAll($access_level="") {
		if($access_level="" || $access_level=="facility_administrator"){
			$query = Doctrine_Query::create() -> select("*") -> from("Regimen_Change_Purpose");
		}
		else{
			$query = Doctrine_Query::create() -> select("*") -> from("Regimen_Change_Purpose") -> where("Active", "1");
		}
		$purposes = $query -> execute();
		return $purposes;
	}
	
	public static function getSource($id) {
		$query = Doctrine_Query::create() -> select("*") -> from("Regimen_Change_Purpose") -> where("id = '$id'");
		$ois = $query -> execute();
		return $ois[0];
	}

}
?>
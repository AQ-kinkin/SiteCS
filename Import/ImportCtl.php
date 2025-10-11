<?php

#include("database.class.php");
    
    class ImportCtl
    {
        public const EXCEL_COL_NUMCOMPTE = 0;
        public const EXCEL_COL_LIBCOMPTE = 1;
        public const EXCEL_COL_KEY       = 2;
        public const EXCEL_COL_LIBELLE   = 3;
        public const EXCEL_COL_VOUCHIER  = 4;
        public const EXCEL_COL_FOURNI    = 5;
        public const EXCEL_COL_DATE      = 6;
        public const EXCEL_COL_TVA       = 7;
        public const EXCEL_COL_CHARGES   = 8;
        public const EXCEL_COL_TTC       = 9;
        
        public $error;
        
        private $objDB;
        private $table_name;
        private $NameColLst = array( 'N°Compte', 'Compte', 'Clé', 'Libelle', 'Pièce', 'Fournisseur', 'Date', 'dont TVA', 'Charges' , 'Montant TTC' );
        private $counts = [
            '001' => 0,
            '003' => 0,
            '004' => 0,
            '005' => 0,
            '006' => 0,
            '007' => 0,
            '008' => 0,
            '009' => 0,
            '010' => 0,
            '011' => 0,
            '014' => 0,
            '999' => 0
        ];
        
        public function __construct($ObjDB)
        {
            // Set objet de contrôle
            $this->objDB = $ObjDB;
        }

        public function check_Excel_import($worksheet)
        {
            $answer = array();
            foreach ([ImportCtl::EXCEL_COL_NUMCOMPTE, ImportCtl::EXCEL_COL_LIBCOMPTE, ImportCtl::EXCEL_COL_KEY, ImportCtl::EXCEL_COL_LIBELLE, ImportCtl::EXCEL_COL_VOUCHIER, ImportCtl::EXCEL_COL_FOURNI, ImportCtl::EXCEL_COL_DATE, ImportCtl::EXCEL_COL_TVA, ImportCtl::EXCEL_COL_CHARGES, ImportCtl::EXCEL_COL_TTC] as $val) {
                try { $this->_check_Excel_import( $worksheet, $val ); }
                catch (Exception $e) {
                    array_push($answer, $e->getMessage());
                }
            }
            return $answer;
        }
        
        private function _check_Excel_import($worksheet, $position)
        {
            $value_excel = str_replace([ "\n", "\r", ' ' ], '', $worksheet->getCell([$position+1, 1])->getValue());
            if ($value_excel !== $this->NameColLst[$position])
            {
                throw new Exception("La colone $position contient $value_excel au lieu de " . $this->NameColLst[$position] . "."); 
            }
        }

        public function prepare_Import_Table()
        {
            $this->table_name = 'IMPORT_EXCEL_' . date('YmdHis');	
            $sql = "CREATE TABLE `csresip1501`.$this->table_name (`Index` VARCHAR(8) NOT NULL, `CompteNum` INT UNSIGNED NULL , `CompteLib` TINYTEXT NULL , `Key` TINYTEXT NOT NULL , `Libelle` TEXT NOT NULL, `NumVoucher` TINYTEXT NOT NULL COMMENT 'N° pièce comptable', `Fournisseur` TINYTEXT NOT NULL, `Date` varchar(10) NOT NULL, `TVA` TINYTEXT NOT NULL, `Charges` TINYTEXT NOT NULL, `TTC` TINYTEXT NOT NULL, PRIMARY KEY (`Index`(8))) ENGINE = InnoDB;";
            #echo "<H1>SQL 1: $sql</H1>";
            $answer = false;

            try
            {
                $count = $this->objDB->exec($sql);

                $sql = "INSERT INTO " . $this->table_name . " VALUES ( :id, :numcompte, :libcompte, :key, :libelle, :numpiece, :fourn, :date, :tva, :charge, :ttc );"; 
                #echo "<H1>SQL 2: $sql</H1>";
                $this->objDB->query($sql);

                $answer = true;
            }
            catch (PDOExection $e)
            {
                $this->error = $e->getMessage();
            }
            
            return $answer;
        }
        
        public function add_import_line($array_values)
		{	
            $answer = false;

            #echo "<H1>SQL : " . $array_values[2] . "</H1>";
			$id_import = $this->get_infos_key($array_values[2]);
            #echo "add_import_line -1- ";
            #print_r($id_import);
            #echo "<br/>";
            if ( isset( $id_import['error'] ) )
            {
                $this->error = $id_import['error'];
            }
            else
            {
                    if ( $this->objDB->execute([
                        ':id' => $id_import['id'],
                        ':numcompte' => $array_values[0],
                        ':libcompte' => $array_values[1],
                        ':key' => $id_import['typekey'],
                        ':libelle' => $array_values[3],
                        ':numpiece' => $array_values[4],
                        ':fourn' => $array_values[5],
                        ':date' => $array_values[6],
                        ':tva' => $array_values[7],
                        ':charge' => $array_values[8],
                        ':ttc' => $array_values[9]
                    ]) )
                    {
                        $answer = true;
                    } 
                    else
                    {
                        $this->error = $id_import['error'];
                    }
            }
            
            return $answer;
		}

        private function get_infos_key($Key_Name)
		{
			$answer = [];

            $result = $this->get_typekey($Key_Name);
            #echo "get_infos_key -1- ";
            #print_r($result);
            #echo "<br/>";
            if ( isset( $result['error'] ) )
            { 
                $answer['error'] = $result['error'];
            }
            else
            {
                $answer['typekey'] = $result['typekey'];
                $answer['id'] = $result['typekey'] . "_". ++$this->counts[$result['typekey']];
                #echo "get_infos_key -2- ";
                #print_r($answer);
                #echo "<br/>";
			}
			
			return $answer;
		}

        public function get_namekey($key_type)
        {
            $answer = [];

			$sql = "SELECT namekey FROM `Compte_Key_type` where typekey = '$key_type';";
			echo "SQL : $sql <br/>";
   
			try
			{
				$result = $this->objDB->execonerow($sql);
                $answer['namekey'] = $result['namekey'];
			}
			catch (PDOExection $e)
			{
				$this->error = $e->getMessage();
                $answer['error'] = $this->error;
			}
			
			return $answer;
        }

        public function get_typekey($Key_Name)
        {
            $answer = [];

			$sql = "SELECT typekey FROM `Compte_Key_type` where namekey = '$Key_Name';";
			#echo "SQL : $sql <br/>";
   
			try
			{
				$result = $this->objDB->execonerow($sql);
                #echo "get_typekey -1- ";
                #print_r($result);
                #echo "<br/>";
                $answer['typekey'] = $result['typekey'];
                #echo "get_typekey -2- ";
                #print_r($answer);
                #echo "<br/>";
			}
			catch (PDOExection $e)
			{
				$this->error = $e->getMessage();
                $answer['error'] = $this->error;
			}
			
			return $answer;
        }

    }

?>
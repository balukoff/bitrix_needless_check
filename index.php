<?

	# класс для получения принадлежности по артикулу

  class Needless{
	  public $list;
	  private $block_id;
	  private $product_list;
	  
	  # входными данными в класс являются $arResult[ITEMS]
	  function __construct($list){
		  $this->list = $list;
		  $this->block_id = 27;
		  $this->start();
	  }
	  # готовим строку запроса
	  function prepareQuery($product_ids){
		$counter = 0;
	   foreach($product_ids as $id){
   	    if(trim($id['sku']) == '') continue;
		$list .= "'".$id['sku']."'"; 
	    if(count($product_ids) - 1 != $counter)
	    $list .= ', ';
	    $counter++;
       }  
	   return '('.$list.')';
	  }
	  
	  # получаем список элементов из инфоблока и список артикулов
	  function getProductIDS(){
		 $product_ids = array();
	   foreach($this->list as $item){
	    $arFilter = array('ID'=>$item['ID'], 'IBLOCK_ID' => $this->block_id, 'ACTIVE' => 'Y');
	    $res = CIBlockElement::GetList(array(), $arFilter, false, false, array());
	    $element = $res->GetNextElement();
	    if($element){
		 $properties = $element->getProperties();
	     $product_ids[] = array('ID' => $item['ID'], 'sku' => $properties["CML2_ARTICLE"]["VALUE"]);
		}
       } 		  
	  $this->product_list = $product_ids;
	  return $product_ids;
	  }
	 
	 function getSKU($ID){
		foreach($this->product_list as $item){
			if($item['ID'] == $ID) return $item['sku'];
		} 
	 }
	 
	 function setQuery(){
		 global $DB;
		 $sql = "select (case when UF_PRINADLEZHNOST is NULL then 0 else UF_PRINADLEZHNOST end) as 'ptype' from b_nomenklatura ";
		 $product_ids = $this->getProductIDS();
		 $list = $this->prepareQuery($product_ids);
	     $select = $DB->Query("$sql where UF_KOD in $list");
		// print_r($arResult);
		 while($row = $select->Fetch()){
		 $counter = 0;
		 
		 foreach($arResult['ITEMS'] as $item){
	      $sku = $this->getSKU($item['ID']);
		  if($sku == $row['UF_KOD']){ 
		   $arResult['ITEMS'][$counter]['NEEDLESS'] = $row['ptype'];
		   $this->list['ITEMS'][$counter]['NEEDLESS'] = $row['ptype'];
		   $counter++;
		  }
	     }
	 
	 }
	  
  } 
   final function start(){
	$this->setQuery(); 	   
   }
  
  }
 
  class SearchNeedless extends Needless{
	 
	 function setQuery(){
		 global $DB;
		 $sql = "select UF_KOD, (case when UF_PRINADLEZHNOST is NULL then 0 else UF_PRINADLEZHNOST end) as 'ptype' from b_nomenklatura ";
		 $product_ids = $this->getProductIDS();
		 $list = $this->prepareQuery($product_ids);
	     if($list == '()'){ // пустой
		  return $this->list = [];
  		 }
		 $select = $DB->Query("$sql where UF_KOD in $list");
		 while($row = $select->Fetch()){

 		 foreach($this->list as $key => $item){
		  $sku = $this->getSKU($item['ID']);
		  if($sku == $row['UF_KOD']){ 
		   $this->list[$key]['NEEDLESS'] = $row['ptype'];
		   
		   break;
		  }
	     }
	 }
	  
  }
    public function getResult(){
		return $this->list;
	}
  }

  interface Sorter{
	  function getArray($data, $field);
	  function iSort();
  }
  
  class SortResult implements Sorter{
	  private $ar;
	  private $result;
	  private $sort_params;
	  
	  
	  function __construct($ar){
		  $this->ar = $ar;
	  }
	  
	  function getArray($data, $field){
		 $datar = array();
		  foreach($data as $key=>$arr){
		  $datar[$key]=$arr[$field];
         }
		 return $datar;
	  }
	  
	  
	  function getAdditionalArray($data, $field){
		 $datar = array();
		  foreach($data as $key=>$arr){
		  $datar[$key]=$arr[$field]['VALUE'];
         }
		 return $datar;
	  }
	  
	  
	 function getTypeOfRequest(){
		 $get = $_GET['sort'];
		 if(!isset($get)) return false;
		 else
			 switch($get){
				 case 'NAME': return 'NAME';break;
				 case 'PRICE': return 'MIN_PRICE';break;
			 }
		  
	  }
	  
	  function getOrder(){
	   if(isset($_GET['order'])) return $_GET['order'];
	   return false;
	  }
	  
	  function localSort(&$array, $order){
	   switch($order){
	    case 'asc': sort($array); break;
		case 'desc': rsort($array); break;
	    default: asort($array); break;
	   }
	   $result = array();
	   foreach($array as $key => $item){
	    $result[] = $item;
	   }
	  $array = $result;
	  }
	  
	 function cmp($a, $b) {
       $orderBy=array($this->sort_params['field1']=>'asc', $this->sort_params['field2']=>$this->sort_params['field2_sort']);
		 $result= 0;
         foreach( $orderBy as $key => $value ){
          if($key == 'NEEDLESS'){
		  if( $a[$key] == $b[$key] ) continue;
          $result= ($a[$key] < $b[$key])? -1 : 1;
          if( $value=='desc' ) $result= -$result;
          break;
          }else{
		   if( $a[$key]['VALUE'] == $b[$key]['VALUE'] ) continue;
           $result= ($a[$key]['VALUE'] < $b[$key]['VALUE'])? -1 : 1;
           if( $value=='desc' ) $result= -$result;
           break;  
		  }
		 
		 }
         return $result;
     }
	  
	  function fullSort(&$array, $field1, $field1_sort = 'asc', $field2, $field2_sort = 'asc'){
		 $this->sort_params['field1']      = $field1;
		 $this->sort_params['field2']      = $field2;
		 $this->sort_params['field2_sort'] = $field2_sort;
		
		 return usort($array, array("SortResult", "cmp"));
	  }
	  
	  
	  function iSort(){
		  $result = $this->ar;
		  $type = $this->getTypeOfRequest();
		  if($type) $field = $type; 
		  $datar = $this->getArray($result, 'NEEDLESS');
		  $vdata = $this->getAdditionalArray($result, $field);
		  
		  if(count($vdata) != 0){
		   $order = $this->getOrder();
		   if($order) $this->localSort($vdata);
			$this->fullSort($result, 'NEEDLESS', 'asc', $field, $order);
		  }
		  else
			  array_multisort($result, SORT_NUMERIC, $datar);
	      
		  return $result;
	  }
	  
	  function getResult(){
		  return $this->iSort();
	  }
	  
  }
  
  class Transliterate{
  function OnBeforeIBlockElementHandler(&$arFields)
  {
   $name = $arFields["NAME"];
   $arParams = array("replace_space"=>"_","replace_other"=>"_");
   $trans = Cutil::translit($name,"ru",$arParams);
   $arFields["CODE"] = $trans;
  }
 }

?>
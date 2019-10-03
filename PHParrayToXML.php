<?php
/*
* This class converts a PHP Multidimentional array to an XML.  
*/

class Model_Util_Conv2Xml extends Model_Dao_Base {

	/**
	 * @var Core_Dao_Asset_Feature
	 */
	private static $_inst = null;
	private static $xmlpath = null;

	public static function getInstance() {
		if (null == self::$_inst)
			self::$_inst = new Model_Util_Conv2Xml();
		return self::$_inst;
	}
	
	public function init()
    {
        /* Initialize action controller here */
		$config = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
		Zend_Registry::set('config', $config);
		$this -> xml_path = $config -> ss -> xmlpath;
		
    }

	
	/*
	 * Generates and saves the xml in the specified location.
	 */
	public function array_to_xml($data_info, &$root) {
		
		foreach ($data_info as $key => $xmldata) {
			#Loops through each node
			#$Key is the node name
			#$xmldata contains the attributes and values for the node names
			
			$node = $xmldata['node'];
			$attribs = $xmldata['attrib'];
			$value = $xmldata['value'];
			

			if (is_array($value)) 
			{
				$cnode = $value['node'];
				$cattribs = $value['attrib'];
				$subnode = $root -> addChild("$node");
				if (isset($attribs)) {
					foreach ($attribs as $atkey => $atval) {
						$lastNodePos = $root -> count() - 1;
						$nodeChildrens = $root -> children();
						$nodeChildrens[$lastNodePos] -> addAttribute($atkey, $atval);
					}
				}
				Model_Util_Conv2Xml::getInstance() -> array_to_xml($value, $subnode);
				
			} else {
				$root -> addChild("$node", "$value");
				//Add attributes
				
				//echo "yakg:  node is $node and value is $value \n";
				if (isset($attribs)) {
					foreach ($attribs as $atkey => $atval) {
						$lastNodePos = $root -> count() - 1;
						$nodeChildrens = $root -> children();
						$nodeChildrens[$lastNodePos] -> addAttribute($atkey, $atval);
					}
				}
			}
		} #End FOR LOOP
	}


	/*
	 * Generate xml
	 * $root: an array containing the parent node, attributes of the node, and the xml array value
	 * $xml_path:  path where the xml needs to be stored
	 * $xml_name:  name of the file that needs to be updated or saved.
	 */
	public function xmlGenerator($root, $xml_path, $xml_name) {
		//data eg( array('price' => array('attrib' => array(''=>''), 'data' => 'value')) )
		$root['node'];
		$root['attrib'];
		$root['value'];
		
		// creating object of SimpleXMLElement
		$xml_convert = new SimpleXMLElement("<?xml version=\"1.0\"?><" . $root['node'] . "></" . $root['node'] . ">");
		foreach ($root['attrib'] as $attkey => $attval) {
			$xml_convert -> addAttribute($attkey, $attval);
		}

		/*
		$overwrites = array('keywords');
		if (file_exists(realpath($xml_path . $xml_name)) && in_array($root['node'], $overwrites)) {
			$xml_convert = simplexml_load_file(realpath($xml_path . $xml_name));
		}
		*/
		

		$this -> array_to_xml($root['value'], $xml_convert);

		//saving generated xml file
		$xml_convert -> asXML($xml_path . $xml_name);
		
		//saving generated json file
		file_put_contents($xml_path . ''. str_replace('xml', 'json', $xml_name), "jsonCallback(".json_encode($root['value']).");" );

	}
	
	
	/*
	 * Sends the xmls to the browser based on the availability of the product at the distributor
	 * $xml: xml object
	 * $file: product, types, keywords etc
	 * $distributor: distirbutor id 
	 * $lang: language id  
	 */
	
	public function xmldefineddata($xml,$file, $distributor, $lang){
		//return $template_xml_content;
		//echo '<pre> <h4>BEFORE</h4>',print_r($xml);
		
		if(!in_array($distributor, array(6,6439))) {
		
		/*** Grab distributor data ***/
		$cachename = sha1($xml->__toString() .'_'.$distributor); //Random generated cachename while separating them using distributor id
		$cache = Zend_Registry::get('Memcache');
		if(($DistributorInfo = $cache -> load($cachename)) === false){
			//No cache found... access data from db
			$DistributorInfo = Model_Dao_Supplies_Distributor::getInstance() -> getInfoByManId('2', $distributor, $lang);
			$cache->save($DistributorInfo, $cachename);  
			//echo 'Not cached </br>';
		} //else echo 'Finally Cached as should</br>';
		
		$DistributorInfo = Model_Dao_Supplies_Distributor::getInstance() -> getInfoByManId('2', $distributor, $lang);
		//echo "<pre>", print_r($DistributorInfo); exit;
		

		/**************************************************/
		/************** Regenerate Keywords xml ***********/
		/**************************************************/

		if($file == 'keywords'){
			$target = '//model';
			foreach($xml->xpath($target) as $nodes)
			{
				$product_id = (int)$nodes['productid'];
				if(!array_key_exists($product_id, $DistributorInfo))
				{
					$dom=dom_import_simplexml($nodes);
		        	$dom->parentNode->removeChild($dom);	
				}
			}
		}

		

		/**************************************************/
		/************** Regenerate Product xml ************/
		/**************************************************/
		/****** Append Product buy link and price to parent product******/
		if($file == 'product'){
			
			foreach($xml->xpath('//model/compatible_products') as $compatible_products){
				foreach($xml->xpath('//model/compatible_products/group') as $group)
				{
					$rm = 0;
					foreach($xml->xpath('//model/compatible_products/group/product') as $product)
					{
						if(array_key_exists((int)$product->{'productid'}, $DistributorInfo))
						{
							//****** Append Product buy link and price to compatible products ******
							$pageurl = htmlspecialchars($DistributorInfo[(int)$product->{'productid'}]['page_url']);
							if(!isset($product->{'buy_url'}))
							$product->addChild("buy_url", $pageurl);
							if(!isset($product->{'price'}))
							$product->addChild("price", $DistributorInfo[(int)$product->{'productid'}]['price']);
							$rm++;
							//echo "Retaining product id : ".(int)$product->{'productid'}."</br>";
						} 
							else 
						{
							//echo "Removing product id : ".(int)$product->{'productid'}."</br>";
							$product_dom = dom_import_simplexml($product);
				        	$product_dom -> parentNode -> removeChild($product_dom);	
						}
						
						//$prdnode->addChild("buy_url", $DistributorInfo[$mpn]['page_url']);
						
					}
					if(count($group->children()) === 0){
						//Remove the group itself
						$group_dom = dom_import_simplexml($group);
				        $group_dom -> parentNode -> removeChild($group_dom);
					}
				}
			}
				
			
			
				$product_id = $xml[0] -> product_id;
				//echo $product_id;exit;
				$xml[0] -> addChild("buy_url", $DistributorInfo[(int)$product_id]['page_url']);	
				$xml[0] -> addChild("price", $DistributorInfo[(int)$product_id]['price']);	
			//exit;
		}
		
		/**************************************************/
		/************** Regenerate Types_# xml ************/
		/**************************************************/
		/*
		if(strpos($file, 'types_') !== false){
			foreach($xml->xpath('//type/series') as $series)
			{
				foreach($xml->xpath('//type/series/model') as $model){
					if(!array_key_exists((int)$model['productid'], $DistributorInfo))
						{
							$model_dom=dom_import_simplexml($model);
		        			$model_dom->parentNode->removeChild($model_dom);
						}
					
				}
				if(count($series->children()) === 0){
					$dom=dom_import_simplexml($series);
		        	$dom->parentNode->removeChild($dom);
				}
			}
		}
		
		*/
		
		if(strpos($file, 'types_') !== false){
			foreach($xml->xpath('//type/series') as $series)
			{
				foreach($xml->xpath('//type/series/model') as $model){
					//if it is a distributors product but there are no supplies for it
					$lagcode = Model_Dao_Supplies_Language::getInstance() -> getCodebyLanguageId($lang); //eg 'en';
					$productxml = $this -> xml_path .''.$lagcode.'/all_models/'.(int)$model['productid'].'.xml';
					@$productxml = simplexml_load_file(realpath($productxml));
					$compatibles = 0;
					if(!empty($productxml)){
						foreach($productxml->xpath('//model/compatible_products') as $compatible_products){
							foreach($productxml->xpath('//model/compatible_products/group') as $group)
							{
								$rm = 0;
								foreach($productxml->xpath('//model/compatible_products/group/product') as $product)
								{
									if(array_key_exists((int)$product->{'productid'}, $DistributorInfo))
									{
										//****** Product has compatible products ******
										$compatibles = 1;
									} 
								}
							}
						}
					}

					/*** Remove the product if it isn't in the distributor list 
					 *** or if the product does not have any compatibles  *****/
					if(!array_key_exists((int)$model['productid'], $DistributorInfo) || $compatibles == 0)
						{
							$model_dom=dom_import_simplexml($model);
		        			$model_dom->parentNode->removeChild($model_dom);
						}
					
				}
				if(count($series->children()) === 0){
					$dom=dom_import_simplexml($series);
		        	$dom->parentNode->removeChild($dom);
				}
			}
		}
		
		
		
		/**************************************************/
		/************** Regenerate Types xml **************/
		/**************************************************/
		
		if($file == 'types'){
			foreach($xml->xpath('//types/type') as $typenodes){
				$language = Model_Dao_Supplies_Language::getInstance() -> getCodebyLanguageId($lang);
				$temp = $checkxml =  $temp = null;
				$url =  'http://media.flixsyndication.net/suppliesfinder/search/xmldata/data/types_'.$typenodes['id'].'/lang/'.$language.'/distributor/'.$distributor;
				$temp = file_get_contents($url);
 				$checkxml = simplexml_load_string($temp);
				//echo "ID ".count($checkxml->{'series'})."<a href='$checkxml'>". $url . "</a></br>";
				if(count($checkxml->{'series'}) == 0) {
					$types_dom=dom_import_simplexml($typenodes);
					$types_dom->parentNode->removeChild($types_dom);
				}
			}
		}
		
		
		} //Checking if the distributor is numeric and allowed
		
		/******** Return resulting xml **********/

		return $xml->asXML();
		
    }

}

class SimpleXMLExtended extends SimpleXMLElement{
	public function addCData($cdata_text) {
	    $node = dom_import_simplexml($this); 
	    $no   = $node->ownerDocument; 
	    $node->appendChild($no->createCDATASection($cdata_text)); 
  } 
}
?>

<?php

$ini = eZINI::instance( 'childrencount.ini' );
$ParentObjectClassID = $ini->variable( "childrencount", "ParentObjectClassID" );
$parentClassAttributeID = $ini->variable( "childrencount", "parentClassAttributeID" );
$childClassID = $ini->variable( "childrencount", "childClassID" );

$rootNode =& eZContentObjectTreeNode::fetch( 2 );

$subTree= $rootNode->subTree(array('ClassFilterType' => 'include', 'ClassFilterArray' => array($ParentObjectClassID)));


foreach ($subTree as $parent)
{
	$subtreeCount= $parent->subTreeCount(array('ClassFilterType' => 'include', 'ClassFilterArray' => array($childClassID)));
	
	if ($subtreeCount != 0)
	{
		$parentID = $parent->attribute("contentobject_id");
	    $contentObject =& eZContentObject::fetch($parentID);
	    
	    $contentObjectVersion =& $contentObject->version($contentObject->attribute( 'current_version' ) );
	    $contentObjectVersion->setAttribute( 'status', EZ_VERSION_STATUS_DRAFT);
	    $contentObjectAttributes =& $contentObjectVersion->contentObjectAttributes();
	    
	    
	    foreach ($contentObjectAttributes as $att)
	    {
	    	if ($att->attribute('contentclass_attribute')->attribute('id') == $parentClassAttributeID)
	    	{
	    		importAttribute( $subtreeCount, $att ); //children count field
	    	}
	    }
	    
	    
	    $operationResult = eZOperationHandler::execute( 'content', 'publish', array( 'object_id' => $contentObject->attribute( 'id' ),
                                                                    'version' => $contentObject->attribute( 'current_version' ) ) );
	}
	
}



function importAttribute( $data, &$contentObjectAttribute, $enhancedObjectRelationClassAttributeId=0)
{
   $contentClassAttribute = $contentObjectAttribute->attribute( 'contentclass_attribute' );
   $dataTypeString = $contentClassAttribute->attribute( 'data_type_string' );
   
   ezDebug::writeDebug( "Converting " . $data . " to expected " . $dataTypeString );
   
   switch( $dataTypeString )
   {
   case 'ezfloat' :
   case 'ezprice' :
      $contentObjectAttribute->SetAttribute( 'data_float', $data );
      $contentObjectAttribute->store();
      break;
   case 'ezboolean' :
   case 'ezdate' :
   case 'ezdatetime' :
   case 'ezinteger' :
   case 'ezsubtreesubscription' :
   case 'eztime' :
   case 'ezobjectrelation':
        $contentObjectAttribute->SetAttribute( 'data_int', $data );
      $contentObjectAttribute->store();
         break;
      case 'ezenhancedobjectrelation': 
           $contentObjectAttribute->SetAttribute( 'data_int', $data );
           $contentObjectAttribute->store();
           eZEnhancedObjectRelationType::addContentObjectRelation( $contentObjectAttribute->attribute('contentobject_id'), 1, $enhancedObjectRelationClassAttributeId, $data );
         break;
    case 'ezselection':
         $contentObjectAttribute->SetAttribute( 'data_text', $data );
         $contentObjectAttribute->store();
         break;
   case 'ezemail' :
   case 'ezisbn' :
   case 'ezstring' :
   case 'eztext' :
      $contentObjectAttribute->SetAttribute( 'data_text', $data );
      $contentObjectAttribute->store();
      break;
      
   case 'ezurl' :
      $url_id = eZURL::registerURL ($data);
      if ($url_id) {
         $contentObjectAttribute->SetAttribute( 'data_int', $url_id );
         $contentObjectAttribute->SetAttribute( 'data_text', $data );
         $contentObjectAttribute->store();
      }
      break;
      
   case 'ezxmltext' :
      $inputData = "<?xml version=\"1.0\"?>";
       $inputData .= "<section>";
       $inputData .= "<paragraph>";
       $inputData .= $data;
       $inputData .= "</paragraph>";
       $inputData .= "</section>";
   
       include_once( "kernel/classes/datatypes/ezxmltext/handlers/input/ezsimplifiedxmlinput.php" );
       $dumpdata = "";
       $simplifiedXMLInput = new eZSimplifiedXMLInput( $dumpdata, null, null );
       $inputData = $simplifiedXMLInput->convertInput( $inputData );
       $description = $inputData[0]->toString();
       $contentObjectAttribute->setAttribute( 'data_text', $description );
       $contentObjectAttribute->store();
      break;
   case 'ezkeyword':
      $key = new eZKeyword();
      $key->initializeKeyword($data);
      $key->store($contentObjectAttribute) ;
      $contentObjectAttribute->SetAttribute( 'data_text', $data );
      $contentObjectAttribute->store();
      break;
   default :
      die( 'Can not store ' . $data . ' as datatype: ' . $dataTypeString );
   }
}
?>
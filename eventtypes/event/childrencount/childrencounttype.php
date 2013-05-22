<?php
//
// ## BEGIN COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
// SOFTWARE NAME: eZ publish childrencount extension
// SOFTWARE RELEASE: 0.1
// COPYRIGHT NOTICE: Copyright (C) 2008 land in sicht
// SOFTWARE LICENSE: GNU General Public License v2.0
// NOTICE: >
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of version 2.0  of the GNU General
//   Public License as published by the Free Software Foundation.
//
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
//
//   You should have received a copy of version 2.0 of the GNU General
//   Public License along with this program; if not, write to the Free
//   Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
//   MA 02110-1301, USA.
//
//
// ## END COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
//

include_once( 'kernel/classes/ezworkflowtype.php' );
include_once( 'kernel/classes/ezcontentobject.php' );
include_once( 'kernel/classes/ezcontentobjecttreenode.php' );
include_once( 'lib/ezutils/classes/ezoperationhandler.php' );

class ChildrenCountType extends eZWorkflowEventType
{
    function ChildrenCountType()
    {
        $this->eZWorkflowEventType( 'childrencount', ezi18n( 'extension/projects', 'Count children 2 attribute' ) );
        $this->setTriggerTypes( array( 'content' => array( 'publish' => array( 'after' ) ) ) );
    }

    function typeFunctionalAttributes()
    {
         return array( );
    }


    function execute( &$process, &$event )
    {
        $parameters = $process->attribute( 'parameter_list' );
        $object =& eZContentObject::fetch( $parameters['object_id'] );
        
        $main_node =& $object->attribute("main_node");
        
        $parentNode =& eZContentObjectTreeNode::fetch( $main_node->attribute("parent_node_id") );
//        $parentNode =& $node->attribute("parent");
        
        //TODO, use INI @see cronjob
        $subtreeCount= $parentNode->subTreeCount(array('ClassFilterType' => 'include', 'ClassFilterArray' => array('25')));
        
        $parentID = $parentNode->attribute("contentobject_id");
        $contentObject =& eZContentObject::fetch($parentID);
        
        $contentObjectVersion =& $contentObject->version($contentObject->attribute( 'current_version' ) );
        $contentObjectVersion->setAttribute( 'status', EZ_VERSION_STATUS_DRAFT);
        $contentObjectAttributes =& $contentObjectVersion->contentObjectAttributes();
        
        
        //TODO, use INI @see cronjob
        importAttribute( $subtreeCount, $contentObjectAttributes[25] ); //children count field
        
        
        $operationResult = eZOperationHandler::execute( 'content', 'publish', array( 'object_id' => $contentObject->attribute( 'id' ),
                                                                    'version' => $contentObject->attribute( 'current_version' ) ) );

        return EZ_WORKFLOW_TYPE_STATUS_ACCEPTED;
    }
}

eZWorkflowEventType::registerType( 'childrencount', 'ChildrenCountType' );

//TODO: duplicate @see cronjob
function importAttribute( $data, &$contentObjectAttribute, $enhancedObjectRelationClassAttributeId=0)
{
   $contentClassAttribute = $contentObjectAttribute->attribute( 'contentclass_attribute' );
   $dataTypeString = $contentClassAttribute->attribute( 'data_type_string' );
   
   
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

<?

include_once __DIR__ . '/../libs/base.php';
include_once __DIR__ . '/../libs/includes.php';


define( 'RFKEY_Property_Devices', 'Devices' );

class rfkeyKonfigurator extends ErgoIPSModule {


    /***********************************************************************
    
    * customized debug methods

    ************************************************************************/

    /*
        debug on/off is a defined constant
    */
    protected function IsDebug()
    {
        return RFKEY_Debug;
    }
    
    /*
        sender for debug messages is set
    */
    protected function GetLogID()
    {
        return IPS_GetName( $this->InstanceID );
    }


    /***********************************************************************
    
    * standard module methods

    ************************************************************************/

    /*
        basic setup
    */
    public function Create()
    {
        parent::Create();

        // connect to existing rf:key Gateway, or create new instance
        $this->ConnectParent( RFKEY_Instance_GUID );
    }


    /***********************************************************************
    
    * module helper methods

    ************************************************************************/

    /*
        provide parent instance
    */
    private function GetParentInstance()
    {
        return IPS_GetInstance( $this->InstanceID )[ "ConnectionID" ];
    }
    
    
    /***********************************************************************
    
    * form layout and interaction logic

    ************************************************************************/

    /*
        create instance from selection
    */
    public function TableClick( $Device )
    {
        // instance exists?
        if ( '-' != $Device[ "InstanceID" ] )
        {
            echo "Instance for this adress already exists";
            return;
        }
        // instance created, but not updated in list?
        $ParentID = $this->GetParentInstance();
        foreach( IPS_GetInstanceListByModuleID( RFKEYR_Instance_GUID ) as $ReaderInstance )
        {
            if ( IPS_GetInstance( $ReaderInstance )[ "ConnectionID" ] == $ParentID )
            {
                // reader instance already exists
                $InstanceReaderAdress = IPS_GetProperty( $ReaderInstance, "ReaderAdress" );
                if ( $InstanceReaderAdress == hexdec( $Device[ "Adress" ] ) )
                {
                    echo "Instance for this adress already created, please re-open the configurator page";
                    return;
                }
            }
        }
        
        $InstanceID = IPS_CreateInstance( RFKEYR_Instance_GUID );
        if ( '' == $Device[ "rfkeyName" ] )
        {
            $DeviceName = "rfkey Reader";
        }
        else
        {
            $DeviceName = $Device[ "rfkeyName" ];
            IPS_SetProperty( $InstanceID, "ReaderName", $DeviceName );
        }
        $DeviceName .= " (" . $Device[ "rfkeyType" ] . " - " . $Device[ "Adress" ] . ")";
        IPS_SetName( $InstanceID, $DeviceName );
        IPS_SetParent( $InstanceID, 0 );
        IPS_SetProperty( $InstanceID, "ReaderAdress", hexdec( $Device[ "Adress" ] ) );
        IPS_ApplyChanges( $InstanceID );
        
        // make sure to connect to the right gateway
        IPS_DisconnectInstance( $InstanceID );
        IPS_ConnectInstance( $InstanceID, $ParentID );
        echo "Instance, created, please re-open the configurator page";
    }

    /*
        create configuration form with table of existing/found card readers
    */
    public function GetConfigurationForm()
    {
        $this->LogDebug( "Entering GetConfigurationForm()" );
        $TableValues = array();
        
        // get reader config from parent instance
        $ParentID = $this->GetParentInstance();
        if ( 0 != $ParentID )
        {
            $ReaderStatus = RFKEY_GetCardReaderStatus( $ParentID );
            if ( !empty( $ReaderStatus ) )
            {
                foreach ( $ReaderStatus as $key => $Reader )
                {
                    $ReaderStatus[ $key ][ "Status" ][ "IPSName" ] = '';
                    $ReaderStatus[ $key ][ "Status" ][ "Existing" ] = 0;
                    $ReaderStatus[ $key ][ "Status" ][ "InstanceID" ] = '-';
                }

                // search existing reader instances connected to our parent
                foreach( IPS_GetInstanceListByModuleID( RFKEYR_Instance_GUID ) as $ReaderInstance )
                {
                    if ( IPS_GetInstance( $ReaderInstance )[ "ConnectionID" ] == $ParentID )
                    {
                        // reader instance already exists
                        $ReaderAdress = IPS_GetProperty( $ReaderInstance, "ReaderAdress" );
                        $ReaderName = IPS_GetProperty( $ReaderInstance, "ReaderName" );
                        foreach ( $ReaderStatus as $key => $Reader )
                        {
                            if ( $ReaderAdress == $Reader[ "Adress" ] )
                            {
                                $ReaderStatus[ $key ][ "Status" ][ "IPSName" ] = IPS_GetName( $ReaderInstance );
                                $ReaderStatus[ $key ][ "Status" ][ "Name" ] = $ReaderName;
                                $ReaderStatus[ $key ][ "Status" ][ "Existing" ] = 1;
                                $ReaderStatus[ $key ][ "Status" ][ "InstanceID" ] = $ReaderInstance;
                            }
                        }
                    }
                }
                // format to form array
                foreach ( $ReaderStatus as $key => $Reader )
                {
                    if ( 1 == $Reader[ "Status" ][ "Existing" ] )
                        $Color = "#9FF781";
                    else
                        $Color = "#FFFFFF";
                    $ReaderArray= array(
                        "InstanceID" => $Reader[ "Status" ][ "InstanceID" ],
                        "Name" => $Reader[ "Status" ][ "IPSName" ],
                        "rfkeyName" => $Reader[ "Status" ][ "Name" ],
                        "rfkeyType" => $Reader[ "Status" ][ "ReaderTypeName" ],
                        "Adress" => '0x' . dechex( $Reader[ "Adress" ] ),
                        "rowColor" => $Color
                    );
                    $TableValues[] = $ReaderArray;
                }
            }
        }

        $this->LogDebug( "Ready to return form" );
        
        $Form = array(
            "actions" => array(
                array( "type" => "List",
                        "name" => "Devices",
                        "caption" => "Kartenleser",
                        "rowCount" => 5,
                        "sort" => array( "column" => "Name", "direction" => "ascending" ),
                        "columns" => array(
                            array( "label" => "Instanz-ID",
                                "name" => "InstanceID", 
                                "width" => "65px"
                                ),
                            array( "label" => "IPS Name",
                                "name" => "Name",
                                "width" => "auto"
                                ),
                            array( "label" => "rf:key-Name",
                                "name" => "rfkeyName",
                                "width" => "100px"
                                ),
                            array( "label" => "rf:key-Typ",
                                "name" => "rfkeyType",
                                "width" => "80px"
                                ),
                            array( "label" => "Adresse",
                                "name" => "Adress",
                                "width" => "60px"
                                ),
                            ),
                      /*
                        "values" => array(
                            array( "InstanceID" => 12345,
                                "Name" => "Leser 1",
                                "rfkeyName" => "rf:key-Leser",
                                "Adresse" => "07CC",
                                "rowColor" => "#ffffff"
                            )
                        ),
                        */
                      "values" => $TableValues,
                    ),
                array( "type" => "Button",
                        "label" => "Instanz erstellen",
                        "onClick" => "RFKEY_TableClick(\$id, \$Devices);"
                    )

            ),
            "status" => array(
                array( "code" =>"101", "icon" => "active", "caption" => "Instanz wird erstellt" ),
                array( "code" =>"102", "icon" => "active", "caption" => "Instanz ist OK" ),
                array( "code" =>"103", "icon" => "inactive", "caption" => "Inaktiv, bitte die übergeordnete Insanz prüfen" )
            )
            );
    return json_encode( $Form );
    }
    
}

?>

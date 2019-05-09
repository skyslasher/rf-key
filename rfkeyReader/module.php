<?

include_once __DIR__ . '/../libs/base.php';
include_once __DIR__ . '/../libs/includes.php';


define( 'RFKEY_RegVar_DoorRelay', 'DoorRelay' );
define( 'RFKEY_RegVar_Active', 'Active' );
define( 'RFKEY_RegVar_Sabotage', 'Sabotage' );
define( 'RFKEY_RegVar_LED', 'LED' );
define( 'RFKEY_RegVar_Buzzer', 'Buzzer' );
define( 'RFKEY_RegVar_LastSuccesfulTransponderID', 'LastSuccesfulTransponderID' );
define( 'RFKEY_RegVar_LastSuccesfulTransponderName', 'LastSuccesfulTransponderName' );
define( 'RFKEY_RegVar_LastTransponderID', 'LastTransponderID' );
define( 'RFKEY_RegVar_LastTransponderName', 'LastTransponderName' );
define( 'RFKEY_RegVar_LastPINCode', 'LastPINCode' );
    
define( 'RFKEY_Property_ReaderName', 'ReaderName' );
define( 'RFKEY_Property_ReaderTypeName', 'ReaderTypeName' );
define( 'RFKEY_Property_ReaderAdress', 'ReaderAdress' );
define( 'RFKEY_Property_DoorRelayDefault', 'DoorRelayDefault' );
define( 'RFKEY_Property_DoorRelayMax', 'DoorRelayMax' );
define( 'RFKEY_Property_BuzzerDefault', 'BuzzerDefault' );
define( 'RFKEY_Property_BuzzerMax', 'BuzzerMax' );
define( 'RFKEY_Property_LogSize', 'LogSize' );
define( 'RFKEY_Property_PersistentTransponderLog', 'PersistentTransponderLog' );

define( 'RFKEY_RuntimePersistence_TransponderLog', 'TransponderLog' );

define( 'RFKEY_StatusTimer_DoorRelay', 'StatusDoorRelay' );
define( 'RFKEY_StatusTimer_Buzzer', 'StatusBuzzer' );

class rfkeyReader extends ErgoIPSModule {


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

        // create status variables

        // door relay, status and interactive
        $InstanceID = $this->RegisterVariableBoolean(
            RFKEY_RegVar_DoorRelay,
            'Tür',
            '~Lock.Reversed',
            $this->InstanceID
        );
        IPS_SetPosition( $InstanceID, 2 );
        // red led, status and interactive
        $InstanceID = $this->RegisterVariableBoolean(
            RFKEY_RegVar_LED,
            'Rote LED',
            '~Switch',
            $this->InstanceID
        );
        IPS_SetPosition( $InstanceID, 3 );
        // buzzer, status and interactive
        $InstanceID = $this->RegisterVariableBoolean(
            RFKEY_RegVar_Buzzer,
            'Summer',
            '~Switch',
            $this->InstanceID
        );
        IPS_SetPosition( $InstanceID, 4 );
        // active
        $InstanceID = $this->RegisterVariableBoolean(
            RFKEY_RegVar_Active,
            'Aktiv',
            '~Switch',
            $this->InstanceID
        );
        IPS_SetPosition( $InstanceID, 5 );
        // sabotage
        $InstanceID = $this->RegisterVariableBoolean(
            RFKEY_RegVar_Sabotage,
            'Sabotagekontakt',
            '~Alert',
            $this->InstanceID
        );
        IPS_SetPosition( $InstanceID, 6 );
        // last successful transponder ID
        $InstanceID = $this->RegisterVariableString(
            RFKEY_RegVar_LastSuccesfulTransponderID,
            'Letzte autorisierte Transponder-ID',
            '',
            $this->InstanceID
        );
        IPS_SetPosition( $InstanceID, 7 );
        // last successful transponder name
        $InstanceID = $this->RegisterVariableString(
            RFKEY_RegVar_LastSuccesfulTransponderName,
            'Letzter autorisierter Transponder',
            '',
            $this->InstanceID
        );
        IPS_SetPosition( $InstanceID, 8 );
        // last transponder ID
        $InstanceID = $this->RegisterVariableString(
            RFKEY_RegVar_LastTransponderID,
            'Letzte Transponder-ID',
            '',
            $this->InstanceID
        );
        IPS_SetPosition( $InstanceID, 9 );
        // last transponder name
        $InstanceID = $this->RegisterVariableString(
            RFKEY_RegVar_LastTransponderName,
            'Letzter Transponder',
            '',
            $this->InstanceID
        );
        IPS_SetPosition( $InstanceID, 10 );
        // last PIN code
        $InstanceID = $this->RegisterVariableString(
            RFKEY_RegVar_LastPINCode,
            'Letzter PIN Code',
            '',
            $this->InstanceID
        );
        IPS_SetPosition( $InstanceID, 11 );

        // enable these variables for webfront actions
        $this->EnableAction( RFKEY_RegVar_DoorRelay );
        $this->EnableAction( RFKEY_RegVar_LED );
        $this->EnableAction( RFKEY_RegVar_Buzzer );
        
        // create configuration properties
        $this->RegisterPropertyString( RFKEY_Property_ReaderName, 'unbekannter Name' );
        $this->RegisterPropertyString( RFKEY_Property_ReaderTypeName, 'unbekannter Typ' );
        $this->RegisterPropertyInteger( RFKEY_Property_ReaderAdress, 0 );
        $this->RegisterPropertyInteger( RFKEY_Property_DoorRelayDefault, RFKEY_Default_Relais_Time_hms / 10 );
        $this->RegisterPropertyInteger( RFKEY_Property_DoorRelayMax, RFKEY_Default_Max_Relais_Time_hms / 10 );
        $this->RegisterPropertyInteger( RFKEY_Property_BuzzerDefault, RFKEY_Default_Buzz_Time_hms / 10 );
        $this->RegisterPropertyInteger( RFKEY_Property_BuzzerMax, RFKEY_Default_Max_Buzz_Time_hms / 10 );
        $this->RegisterPropertyInteger( RFKEY_Property_LogSize, 100 );
        $this->RegisterPropertyString( RFKEY_Property_PersistentTransponderLog, '' );

        // initialize persistence
        $this->SetBuffer( RFKEY_RuntimePersistence_TransponderLog, '' );
        
        // create timer
        // we do not get back status info on door relay and buzzer, so we emulate them with timers
        $this->RegisterTimer(
            RFKEY_StatusTimer_DoorRelay,
            0,
            'RFKEY_StatusUpdate( $_IPS["TARGET"], "' . RFKEY_StatusTimer_DoorRelay .'" );'
        ); // no update on init
        $this->RegisterTimer(
            RFKEY_StatusTimer_Buzzer,
            0,
            'RFKEY_StatusUpdate( $_IPS["TARGET"], "' . RFKEY_StatusTimer_Buzzer . '" );'
        ); // no update on init

        // subscribe to IPS messages
        $this->RegisterMessage( 0, 10001 ); // IPS_KERNELSTARTED
        $this->RegisterMessage( 0, 10002 ); // IPS_KERNELSHUTDOWN
        
        // connect to existing rf:key Gateway, or create new instance
        $this->ConnectParent( RFKEY_Instance_GUID );
    }

    /*
        react on subscribed IPS messages
    */
    public function MessageSink( $TimeStamp, $SenderID, $Message, $Data )
    {
        switch ( $Message )
        {
            case 10001: // IPS_KERNELSTARTED
                $this->SetTransponderLog( $this->GetPersistentTransponderLog() );
                $this->SetPersistentTransponderLog( "" );
                break;
            case 10002: // IPS_KERNELSHUTDOWN
                // write log to persistence
                $this->SetPersistentTransponderLog( $this->GetTransponderLog() );
                break;
        }
    }

    /*
        set receive filter
    */
    public function ApplyChanges()
    {
        parent::ApplyChanges();  

        // filter on messages only for this adress
        $this->SetReceiveDataFilter( ".*" . $this->GetReaderAdress() . ".*" );
    }


    /***********************************************************************
    
    * access methods to persistence

    ************************************************************************/

    // property persistence (lasts across restarts)
    private function GetReaderName()
    {
        return $this->ReadPropertyString( RFKEY_Property_ReaderName );
    }
    /*
        store reader name when caught by bus traffic
    */
    private function SetReaderName( $Value )
    {
        if ( $Value == $this->GetReaderName() )
            return;
        IPS_SetProperty( $this->InstanceID, RFKEY_Property_ReaderName, $Value );
        IPS_ApplyChanges( $this->InstanceID );
    }
    private function GetReaderTypeName()
    {
        return $this->ReadPropertyString( RFKEY_Property_ReaderTypeName );
    }
    /*
        store reader type name when caught by bus traffic
    */
    private function SetReaderTypeName( $Value )
    {
        if ( $Value == $this->GetReaderTypeName() )
            return;
        IPS_SetProperty( $this->InstanceID, RFKEY_Property_ReaderTypeName, $Value );
        IPS_ApplyChanges( $this->InstanceID );
    }
    private function GetReaderAdress()
    {
        return $this->ReadPropertyInteger( RFKEY_Property_ReaderAdress );
    }
    private function GetDoorRelayDefault()
    {
        return $this->ReadPropertyInteger( RFKEY_Property_DoorRelayDefault );
    }
    private function GetDoorRelayMax()
    {
        return $this->ReadPropertyInteger( RFKEY_Property_DoorRelayMax );
    }
    private function GetBuzzerDefault()
    {
        return $this->ReadPropertyInteger( RFKEY_Property_BuzzerDefault );
    }
    private function GetBuzzerMax()
    {
        return $this->ReadPropertyInteger( RFKEY_Property_BuzzerMax );
    }
    private function GetLogSize()
    {
        return $this->ReadPropertyInteger( RFKEY_Property_LogSize );
    }
    /*
        transponder log goes persistent between restarts
    */
    private function GetPersistentTransponderLog()
    {
        return $this->ReadPropertyString( RFKEY_Property_PersistentTransponderLog );
    }
    private function SetPersistentTransponderLog( $Value )
    {
        IPS_SetProperty( $this->InstanceID, RFKEY_Property_PersistentTransponderLog, $Value );
        IPS_ApplyChanges( $this->InstanceID );
    }
    
    /*
        rotating log
    */
    private function UpdateTransponderLog( $LogEntry )
    {
        $Log = $this->GetTransponderLog();
        $MaxLogSize = $this->GetLogSize();
        $LineCount = substr_count( $Log, "\n" );
        if ( $LineCount >= $MaxLogSize )
            $Log = substr( $Log, strpos( $Log, "\n" ) +1 );
        $Log .= date_format( date_create(), 'Y-m-d H:i:s - ' ) . $LogEntry . "\n";
        $this->SetTransponderLog( $Log );
    }
    private function SetTransponderLog( $Value )
    {
        $this->SetBuffer( RFKEY_RuntimePersistence_TransponderLog, $Value );
    }

    /*
        configuration form - reader name and reader type name are substituted to current values
    */
    public function GetConfigurationForm()
    {
        $Form = file_get_contents( __DIR__ . '/form.json' );
        return str_replace( array( "%Reader_Name%", "%Reader_Type%" ), array( $this->GetReaderName(), $this->GetReaderTypeName() ), $Form );
    }

    /***********************************************************************
    
    * data flow from/to the gateway instance

    ************************************************************************/

    public function ReceiveData( $JSONString )
    {
        $Data = json_decode( $JSONString, true );
        if ( ( RFKEY_TX == $Data[ "DataID" ] ) && ( $this->GetReaderAdress() == $Data[ "Adress" ] ) )
        {
//            $this->LogDebug( 'Recv: ' . print_r( $Data, true ) );
            if ( array_key_exists( "Status", $Data ) )
                $this->SetFromStatusMessage( $Data[ "Status" ] );
            if ( array_key_exists( "Log", $Data ) )
                $this->SetFromLogMessage( $Data[ "Log" ] );
            if ( array_key_exists( "Notification", $Data ) )
                $this->SetFromNotificationMessage( $Data[ "Notification" ] );
            if ( array_key_exists( "ACK", $Data ) )
                $this->SetFromACKMessage( $Data[ "ACK" ] );
        }
    }
    
    private function SendCommand( $Command )
    {
        $Command = array_merge( array( "Adress" => $this->GetReaderAdress() ), $Command );
        // do we have a parent connection?
        if ( IPS_GetInstance( $this->InstanceID )[ "ConnectionID" ] > 0 )
            $this->SendDataToParent(
                json_encode( array_merge( array( "DataID" => RFKEY_RX), $Command ) )
            );
    }


    /***********************************************************************
    
    * methods for status variable handling and updates

    ************************************************************************/

    /*
        proxy function for all variable updates
    */
    private function _SetVar( $Ident, $Status )
    {
        $VarID = $this->GetIDForIdent( $Ident );
        SetValue( $VarID, $Status );
    }
    // all variable updates
    private function SetDoorRelay( $Status )
    {
        $this->_SetVar( RFKEY_RegVar_DoorRelay, $Status );
    }
    private function SetActive( $Status )
    {
        $this->_SetVar( RFKEY_RegVar_Active, $Status );
    }
    private function SetSabotage( $Status )
    {
        $this->_SetVar( RFKEY_RegVar_Sabotage, $Status );
    }
    private function SetLED( $Status )
    {
        $this->_SetVar( RFKEY_RegVar_LED, $Status );
    }
    private function SetBuzzer( $Status )
    {
        $this->_SetVar( RFKEY_RegVar_Buzzer, $Status );
    }
    private function SetLastSuccesfulTransponderID( $Status )
    {
        $this->_SetVar( RFKEY_RegVar_LastSuccesfulTransponderID, $Status );
    }
    private function SetLastSuccesfulTransponderName( $Status )
    {
        $this->_SetVar( RFKEY_RegVar_LastSuccesfulTransponderName, $Status );
    }
    private function SetLastTransponderID( $Status )
    {
        $this->_SetVar( RFKEY_RegVar_LastTransponderID, $Status );
    }
    private function SetLastTransponderName( $Status )
    {
        $this->_SetVar( RFKEY_RegVar_LastTransponderName, $Status );
    }
    private function SetLastPINCode( $Status )
    {
        $this->_SetVar( RFKEY_RegVar_LastPINCode, $Status );
    }

    /*
        perform variable default action
    */
    public function RequestAction( $Ident, $Value )
    {
        switch( $Ident )
        {
            case RFKEY_RegVar_DoorRelay:
                // issue command
                if ( $Value )
                    $this->OpenDoorRelayDefault();
                else
                    $this->CloseDoorRelay();
                break;
            case RFKEY_RegVar_LED:
                // issue command
                $this->SwitchLED( $Value );
                break;
            case RFKEY_RegVar_Buzzer:
                // issue command
                $this->SwitchBuzzer( $Value );
                break;
        }
    }

    /*
        update from status message data
    */
    private function SetFromStatusMessage( $Data )
    {
        // $this->LogDebug( "SetFromStatusMessage: " . json_encode( $Data ) );
        $this->SetActive( $Data[ "ReaderActive" ] );
        $this->SetSabotage( $Data[ "Sabotage" ] );
        $this->SetReaderTypeName( $Data[ "ReaderTypeName" ] );
    }
    
    /*
        update from log message data
    */
    private function SetFromLogMessage( $Data )
    {
        // $this->LogDebug( "SetFromLogMessage: " . json_encode( $Data ) );
        $this->SetReaderName( $Data[ "ReaderName" ] );
        $this->UpdateTransponderLog( $Data[ "Text" ] );
        if ( !array_key_exists( "Access", $Data ) )
            return;
        if ( 1 == $Data[ "Access" ] )
        {
            $this->SetLastSuccesfulTransponderID( $Data[ "TagUID" ] );
            $this->SetLastSuccesfulTransponderName($Data[ "TagName" ] );
        }
        // red LED is switched off by the reader, update variable status
        $this->SetLED( false );
        $this->SetLastTransponderID( $Data[ "TagUID" ] );
        $this->SetLastTransponderName( $Data[ "TagName" ] );
    }

    /*
        update from notification messages
    */
    private function SetFromNotificationMessage( $Data )
    {
        $this->SetReaderName( $Data[ "ReaderName" ] );
        $this->UpdateTransponderLog( $Data[ "Text" ] );
        
        switch ( $Data[ "Notify" ] )
        {
            case "TAG": // tag/transponder found
                $this->SetLastTransponderID( $Data[ "TagUID" ] );
                $this->SetLastPINCode( $Data[ "PINCode" ] );
                break;
            case "SAB": // sabotage found
                break;
            case "ROP": // relay opened
                $this->SetDoorRelay( true );
                // red LED is switched off by the reader, update variable status
                $this->SetLED( false );
                break;
            case "RCL": // relay closed
                $this->SetDoorRelay( false );
                break;
        }
        if ( !array_key_exists( "Access", $Data ) )
            return;
        if ( 1 == $Data[ "Access" ] )
        {
            $this->SetLastSuccesfulTransponderID( $Data[ "TagUID" ] );
            $this->SetLastSuccesfulTransponderName($Data[ "TagName" ] );
        }
        $this->SetLastTransponderID( $Data[ "TagUID" ] );
        $this->SetLastTransponderName( $Data[ "TagName" ] );
    }

    /*
        update from ack message data
    */
    private function SetFromACKMessage( $Data )
    {
        // $this->LogDebug( "SetFromACKMessage: " . json_encode( $Data ) );
        switch( $Data[ "Command" ] )
        {
            case "ODR":
                $this->SetTimerInterval( RFKEY_StatusTimer_DoorRelay,  $Data[ "Duration" ] * 100  );
                $this->SetDoorRelay( true );
                // red LED is switched off by the reader, update variable status
                $this->SetLED( false );
                break;
            case "CDR":
                $this->SetDoorRelay( false );
                break;
            case "BUZZ":
                $this->SetTimerInterval( RFKEY_StatusTimer_Buzzer, $Data[ "Duration" ] * 100 );
                $this->SetBuzzer( true );
                break;
            case "LEDON":
                $this->SetLED( true );
                break;
            case "LEDOFF":
                $this->SetLED( false );
                break;
        }
    }

    /*
        entry point for the status timer
    */
    public function StatusUpdate( $StatusElement )
    {
        // set back status and clear status reset timer
        $this->LogDebug( "Entering StatusUpdate" );
        switch( $StatusElement )
        {
            case RFKEY_StatusTimer_DoorRelay:
                $this->SetTimerInterval( RFKEY_StatusTimer_DoorRelay, 0 );
                $this->SetDoorRelay( false );
                break;
            case RFKEY_StatusTimer_Buzzer:
                $this->SetTimerInterval( RFKEY_StatusTimer_Buzzer, 0 );
                $this->SetBuzzer( false );
                break;
        }
    }


    /***********************************************************************
    
    * methods for script access

    ************************************************************************/

    /*
        open door relay with default opening time
    */
    public function OpenDoorRelayDefault()
    {
        $this->OpenDoorRelay( $this->GetDoorRelayDefault() * 10 );
    }
    
    /*
        open door relay with variable opening time
    */
    public function OpenDoorRelay( $OpenTime = RFKEY_Default_Relais_Time_hms )
    {
        $MaxOpenTime = $this->GetDoorRelayMax() * 10;
        $DefaultOpenTime = $this->GetDoorRelayDefault() * 10;
        
        if ( RFKEY_Max_Duration_hms < $OpenTime )
            $OpenTime = RFKEY_Max_Duration_hms;
        if ( $MaxOpenTime < $OpenTime )
            $OpenTime = $MaxOpenTime;
        if ( 0 == $OpenTime )
            $OpenTime = $DefaultOpenTime;
        
        $this->SendCommand( array(
            "Command" => array(
                "Command" => "ODR",
                "Duration" => $OpenTime
            )
        ) );
    }

    /*
        close door relay
    */
    public function CloseDoorRelay()
    {
        $this->SendCommand( array(
            "Command" => array(
                "Command" => "CDR",
                "Duration" => 0
            )
        ) );
    }
    /*
        turn LED on or off, default duration is infinite ( 0 )
    */
    public function SwitchLED( $State, $Duration = 0 )
    {
        if ( RFKEY_Max_Duration_hms < $Duration )
            $Duration = RFKEY_Max_Duration_hms;
        if ( $State )
        {
            $this->SendCommand( array(
                "Command" => array(
                    "Command" => "LEDON",
                    "Duration" => $Duration
                )
            ) );
        }
        else
        {
            $this->SendCommand( array(
                "Command" => array(
                    "Command" => "LEDOFF",
                    "Duration" => 0
                )
            ) );
        }
    }
    
    /*
        turn buzzer on or off
    */
    public function SwitchBuzzer( $State, $BuzzTime = RFKEY_Default_Buzz_Time_hms )
    {
        $MaxBuzzTime = $this->GetBuzzerMax() * 10;
        $DefaultBuzzTime = $this->GetBuzzerDefault() * 10;

        if ( RFKEY_Max_Duration_hms < $BuzzTime )
            $BuzzTime = RFKEY_Max_Duration_hms;
        if ( $BuzzTime > $MaxBuzzTime )
            $BuzzTime = $MaxBuzzTime;
        if ( 0 == $BuzzTime )
            $BuzzTime = $DefaultBuzzTime;
        if ( $State )
        {
            $this->SendCommand( array(
                "Command" => array(
                    "Command" => "BUZZ",
                    "Duration" => $BuzzTime
                )
            ) );
        }
        else
        {
            $this->SendCommand( array(
                "Command" => array(
                    "Command" => "BUZZ",
                    "Duration" => 1
                )
            ) );
        }
    }

    /*
        get access to transponder logs
    */
    public function GetTransponderLog()
    {
        return $this->GetBuffer( RFKEY_RuntimePersistence_TransponderLog );
    }

    /*
        clear transponder logs
    */
    public function ClearTransponderLog()
    {
        $this->SetTransponderLog( "" );
    }

}

?>

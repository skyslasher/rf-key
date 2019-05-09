<?

include_once __DIR__ . '/../libs/base.php';
include_once __DIR__ . '/../libs/includes.php';


define( 'RFKEY_Property_Status', 'Status' );
define( 'RFKEY_Property_Hostname', 'Hostname' );
define( 'RFKEY_Property_Username', 'Username' );
define( 'RFKEY_Property_Password', 'Password' );
define( 'RFKEY_Property_RelayExtensionCount', 'RelayExtensionCount' );
define( 'RFKEY_Property_OperationsMode', 'OperationsMode' );
define( 'RFKEY_Property_RelayDefault', 'RelayDefault' );
define( 'RFKEY_Property_RelayMax', 'RelayMax' );


class rfkeyGateway extends ErgoIPSModule {

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
    
    * standard module methods

    ************************************************************************/

    /*
        basic setup
    */
    public function Create()
    {
        parent::Create();

        // create configuration properties
        $this->RegisterPropertyBoolean( RFKEY_Property_Status, false );
        $this->RegisterPropertyString( RFKEY_Property_Hostname, "" );
        $this->RegisterPropertyString( RFKEY_Property_Username, "" );
        $this->RegisterPropertyString( RFKEY_Property_Password, "" );
        $this->RegisterPropertyInteger( RFKEY_Property_RelayExtensionCount, 0 );
        $this->RegisterPropertyInteger( RFKEY_Property_OperationsMode, 0 );
        $this->RegisterPropertyInteger( RFKEY_Property_RelayDefault, RFKEY_Default_Relais_Time_hms / 10 );
        $this->RegisterPropertyInteger( RFKEY_Property_RelayMax, RFKEY_Default_Max_Relais_Time_hms / 10 );

        // initialize persistence
        $this->SetBuffer( "LogonStatus",  json_encode( false ) );
        $this->SetBuffer( "ReaderStatus",  "" );
        
        // create timer
        $this->RegisterTimer(
            "Heartbeat",
            1000 * 5 ,
            'RFKEY_Heartbeat( $_IPS["TARGET"] );'
        ); // heartbeat every 5 seconds
        
        // subscribe to IPS messages
        $this->RegisterMessage( 0, 10001 ); // IPS_KERNELSTARTED

        // create new client socket instance and connect
        $this->RequireParent( CSCK_ClientSocket_GUID );
    }

    /*
        set configuration of the parent instance module (the client socket)
    */
    public function GetConfigurationForParent()
    {
        $ConfigArray = array(
            "Host" => $this->GetHostname(),
            "Port" => 1010,
            "Open" => $this->GetMyStatus()
        );
        $Config = json_encode( $ConfigArray );        
        return $Config;
    }

    /*
        react on user configuration dialog
    */
    public function ApplyChanges()
    {
        parent::ApplyChanges();
        
        // find parent -> client socket
        $ParentInstID = $this->GetParentInstance();
        if ( 0 == $ParentInstID )
            return;

        // see if IPS is already up and running
        if ( 10103 == IPS_GetKernelRunlevel() ) // KR_READY
        {
            $ClientSocketConfig = json_decode( IPS_GetConfiguration( $ParentInstID ), true );
            $Status = $this->GetMyStatus();
            $Hostname = $this->GetHostname();

            // opening the client socket without hostname is senseless
            if ( ( '' == $Hostname ) && ( $Status ) )
                $Status = false;

            if ( ( $ClientSocketConfig[ "Host" ] == $Hostname ) && ( $ClientSocketConfig[ "Open" ] == $Status ) )
            {
                // no client socket config change
                // so setting operations mode (which is done after each login) has to be done here
                $this->rfkey_SetOperationsMode( $this->GetOperationsMode() );
            }
            else
            {
                // configuring client socket instance
                // a new login will be performed here if socket is set to open
                IPS_SetConfiguration( $ParentInstID, $this->GetConfigurationForParent() );
                IPS_ApplyChanges( $ParentInstID );
            }
        }
    }

    /*
        react on subscribed IPS messages
    */
    public function MessageSink( $TimeStamp, $SenderID, $Message, $Data )
    {
        switch ( $Message )
        {
            case 10001: // IPS_KERNELSTARTED
                $this->InitReaderStatus();
                break;
        }
    }


    /***********************************************************************
    
    * access methods to persistence

    ************************************************************************/

    // property persistence (lasts across restarts)
    private function GetMyStatus()
    {
        return $this->ReadPropertyBoolean( RFKEY_Property_Status );
    }
    private function GetHostname()
    {
        return $this->ReadPropertyString( RFKEY_Property_Hostname );
    }
    private function GetUsername()
    {
        return $this->ReadPropertyString( RFKEY_Property_Username );
    }
    private function GetPassword()
    {
        return $this->ReadPropertyString( RFKEY_Property_Password );
    }
    private function GetRelayExtensionCount()
    {
        return $this->ReadPropertyInteger( RFKEY_Property_RelayExtensionCount );
    }
    private function GetOperationsMode()
    {
        return $this->ReadPropertyInteger( RFKEY_Property_OperationsMode );
    }
    private function GetRelayDefault()
    {
        return $this->ReadPropertyInteger( RFKEY_Property_RelayDefault );
    }
    private function GetRelayMax()
    {
        return $this->ReadPropertyInteger( RFKEY_Property_RelayMax );
    }

    // buffer persistence (does not last across restarts)
    private function GetLogonStatus()
    {
        return json_decode( $this->GetBuffer( "LogonStatus" ), true );
    }
    private function SetLogonStatus( $Value )
    {
        $this->SetBuffer( "LogonStatus",  json_encode( $Value ) );
    }

    private function SetReaderStatus( $Value )
    {
        $this->SetBuffer( "ReaderStatus",  json_encode( $Value ) );
    }
    private function GetReaderStatus()
    {
        return json_decode( $this->GetBuffer( "ReaderStatus" ), true );
    }

    /*
        init structure and receive reader names (if exist) from child instances
    */
    private function InitReaderStatus()
    {
        $ReaderStatus = array();
        foreach( IPS_GetInstanceListByModuleID( RFKEYR_Instance_GUID ) as $ChInstance )
        {
            if ( IPS_GetInstance( $ChInstance )[ "ConnectionID" ] == $this->InstanceID )
            {
                $ReaderAdress = IPS_GetProperty( $ChInstance, "ReaderAdress" );
                $ReaderName = IPS_GetProperty( $ChInstance, "ReaderName" );
                $ReaderTypeName = IPS_GetProperty( $ChInstance, "ReaderTypeName" );
                $ConfigItem = array(
                    "Adress" => $ReaderAdress,
                    "Status" => array(
                        "Name" => $ReaderName,
                        "ReaderActive" => 0,
                        "ReaderType" => 0,
                        "ReaderTypeName" => $ReaderTypeName,
                        "DoorOpen" => 0,
                        "Sabotage" => 0,
                        "PeripheryError" => 0
                    )
                );
                $ReaderStatus[] = $ConfigItem;
            }
        }
        $this->SetReaderStatus( $ReaderStatus );   
    }


    /***********************************************************************
    
    * conversion methods for the rf:key protocol

    ************************************************************************/

    private function str2hex( $string )
    {
        return substr( chunk_split( bin2hex( $string ), 2, ' ' ), 0, -1 );
    }
    
    private function rfkey_GetByteVal( $data, $offset = 0 )
    {
        return ord( substr( $data, $offset, 1 ) );
    }

    private function rfkey_GetIntVal( $data, $offset = 0 )
    {
        return ord( substr( $data, $offset, 1 ) ) + ( 256 * ord( substr( $data, $offset + 1, 1 ) ) );
    }

    private function rfkey_GetWordVal( $data, $offset )
    {
        return $this->rfkey_GetInt( $data, $offset );
    }

    private function rfkey_GetDoubleWordVal( $data, $offset )
    {
        $LoWord = $this->rfkey_GetWordVal( $data, $offset );
        $HiWord = $this->rfkey_GetWordVal( $data, $offset + 2 );
        return $LoWord + ( 65536 * $HiWord );
    }

    private function rfkey_GetDateTimeVal( $data, $offset )
    {
        $Secs = $this-> rfkey_GetByteVal( $data, $offset );
        $Mins = $this-> rfkey_GetByteVal( $data, $offset + 1 );
        $Hours = $this-> rfkey_GetByteVal( $data, $offset + 2 );
        $Day = $this-> rfkey_GetByteVal( $data, $offset + 3 );
        $Month = $this-> rfkey_GetByteVal( $data, $offset + 4 );
        $Year = $this-> rfkey_GetByteVal( $data, $offset + 5 );
        // since we do not know the timezone of the device, we take the IPS server's timezone
        // consider this accordingly when dealing with these timestamps
        $Timestamp = new DateTime();
        $Timestamp->setDate( $Year, $Month, $Day );
        $Timestamp->setTime( $Hours, $Mins, $Secs );
        return $Timestamp;
    }

    private function rfkey_GetCharVal( $data, $offset, $length )
    {
        $raw_string = substr( $data, $offset, $length );
        // everything down to null-byte
        return strstr( $raw_string, "\0", true );
    }

    private function rfkey_GetUIDVal( $data, $offset )
    {
        $Mode = ord( substr( $data, $offset + 10, 1 ) );
        if ( 0 == $Mode )
        {
            // ASCII
            return strtoupper( $this->rfkey_GetCharVal( $data, $offset, 11 ) );
        }
        else if ( 0xFF == $Mode )
        {
            // Hex
            $UID = '';
            $IsLeadingZero = true;
            for ( $index = 0; $index < 10; $index ++ )
            {
                $string = bin2hex( substr( $data, $offset + $index, 1 ) );
                if ( '00' == $string )
                { 
                    if ( !$IsLeadingZero )
                    $UID .= $string;		
                }
                else
                {
                    $IsLeadingZero = false;
                    $UID .= $string;
                }
            }
            // do uppercase
            return strtoupper( $UID );
        }
        return '';
    }


    /***********************************************************************
    
    * client socket operations

    ************************************************************************/

    /*
        connect socket and log into the rf:key system
    */
    private function rfkey_Connect( $Parent )
    {
        if ( 0 == $Parent )
            return false;
        $this->LogDebug( 'Connecting' );
        if ( $this->rfkey_IsSocketConnected( $Parent ) )
        {
            $this->LogDebug( 'already connected' );
            return true;
        }
        IPS_SetProperty( $Parent, "Open", true );
        IPS_ApplyChanges( $Parent );
        return true;
    }

    /*
        look is socket is connected
    */
    private function rfkey_IsSocketConnected( $Parent )
    {
        if ( 0 == $Parent )
            return false;
        $InstStatus = IPS_GetInstance( $Parent );

        // 104 -> not active, not opened
        // 102 -> socket connected
        // 200 -> socket not connected
        if ( 102 == $InstStatus[ "InstanceStatus" ] )
            return true;
        else
            return false;
    }


    /***********************************************************************
    
    * data flow from/to the rf:key connection

    ************************************************************************/
    
    /*
        receiving rf:key protocol from client socket
    */
    public function ReceiveData( $JSONString )
    {
        $Data = json_decode( $JSONString, true );
        if ( CSCK_RX_GUID == $Data[ "DataID" ] )
        {
            $this->rfkey_ListenHandler( utf8_decode( $Data[ "Buffer" ] ) );
        }
    }

    /*
        sending rf:key protocol to client socket
    */
    private function SendDataRaw( $Data )
    {
        if ( $this->rfkey_IsSocketConnected( $this->GetParentInstance() ) )
            $result = $this->SendDataToParent( json_encode(
                array(
                    "DataID" => CSCK_TX_GUID,
                    "Buffer" => utf8_encode( $Data )
                )
            ) );
    }

    /*
        pad rf:key protocol package with zeros
    */
    private function PadDataPackage( $Data )
    {
        // every data package is 1024 bytes in total
        $Data .= str_pad( '', 1024 - strlen( $Data ), chr( 0x00 ) );
        return $Data;
    }

    /*
        pad package and send data
    */
    private function SendData( $Data )
    {
        // pad and send
        $this->SendDataRaw( $this->PadDataPackage( $Data ) );
    }


    /***********************************************************************
    
    * data flow from/to the reader instances

    ************************************************************************/

    /*
        receiving internal protocol data from connected reader (aka child) instances
    */
    public function ForwardData( $JSONString )
    {
        $Data = json_decode( $JSONString, true );
        if ( RFKEY_RX == $Data[ "DataID" ] )
        {
            if ( array_key_exists( "Adress", $Data ) )
            {
                // look for reader adress in list
                $Readers = $this->GetReaderStatus();
                foreach ( $Readers as $Reader )
                {
                    if ( $Data[ "Adress" ] == $Reader[ "Adress" ] )
                    {
                        if ( array_key_exists( "Command", $Data ) )
                            $this->ExecuteCommand( $Data );
                        break;
                    }
                }
            }
        }
    }

    /*
        sending internal protocol data to connected reader (aka child) instances
    */
    private function SendMessageToReaderInstances( $Message )
    {
        $this->SendDataToChildren(
            json_encode( array_merge( array( "DataID" => RFKEY_TX), $Message ) )
        );
    }


    /***********************************************************************
    
    * communications protocol from/to the reader instances

    ************************************************************************/
    
    /*
        execute commands sent from reader instance
    */
    private function ExecuteCommand( $Data )
    {
        if ( !array_key_exists( "Duration", $Data[ "Command" ] ) )
            return false;
        
        $Adress = $Data[ "Adress" ];
        $Command = $Data[ "Command" ][ "Command" ];
        $Duration = $Data[ "Command" ][ "Duration" ];
        switch( $Command )
        {
            case "ODR": // OpenDoorRelay
                $this->OpenLocalRelay( $Adress, $Duration );
                break;
            case "CDR": // CloseDoorRelay
                $this->CloseLocalRelay( $Adress );
                break;
            case "LEDON": // SwitchLED
                $this->LocalLEDOn( $Adress, $Duration );
                break;
            case "LEDOFF": // SwitchLED
                $this->LocalLEDOff( $Adress );
                break;
            case "BUZZ": // SwitchBuzzer
                $this->Buzz( $Adress, $Duration );
                break;
        }
    }

    /*
        send command execution acknowledgement back to reader instance
    */
    private function rfkey_SendACK( $Adress, $Command, $Duration )
    {
        $ACK = array(
            "Adress" => $Adress,
            "ACK" => array(
                "Command" => $Command,
                "Duration" => $Duration
                )
            );
        $this->SendMessageToReaderInstances( $ACK );
    }

    /*
        ack on reader door relay and/or led ON
    */
    private function rfkey_HandleRelaisLEDOnACK( $Adress, $data )
    {
        $Duration = $this->rfkey_GetIntVal( $data, 4 );
        if ( 0 == $this->rfkey_GetIntVal( $data, 6 ) )
            $this->rfkey_SendACK( $Adress, "ODR", $Duration );
        else
            $this->rfkey_SendACK( $Adress, "LEDON", $Duration );
    }

    /*
        ack on reader door relay and/or led OFF
    */
    private function rfkey_HandleRelaisLEDOffACK( $Adress, $data )
    {
        if ( 0 == $this->rfkey_GetIntVal( $data, 6 ) )
            $this->rfkey_SendACK( $Adress, "CDR", 0 );
        else
            $this->rfkey_SendACK( $Adress, "LEDOFF", 0 );
    }

    /*
        ack on operations mode
    */
    private function rfkey_HandleOperationsModeACK( $Adress )
    {
        // check if successful, then set instance status
    }

    /*
        ack on buzzer
    */
    private function rfkey_HandleBuzzer( $Adress, $data )
    {
        $Duration = $this->rfkey_GetIntVal( $data, 4 );
        $this->rfkey_SendACK( $Adress, "BUZZ", $Duration );
    }
    
    private function rfkey_HandleExternalRelaisACK( $Adress, $data )
    {
        $DoorRelais = $this->rfkey_GetIntVal( $data, 4 );
        $Duration = $this->rfkey_GetIntVal( $data, 6 );
        // future todo: handle localy if we involve status variables in the future
    }

    /*
        convert rf:key status information of connected readers to internal format
        status information comes with every heartbeat
    */
    private function rfkey_GetReaderStatus( $data, $offset )
    {
        $Adress = $this->rfkey_GetIntVal( $data, $offset );
        if ( 0 == $Adress )
            return array();

        $Flags = $this->rfkey_GetByteVal( $data, $offset + 2 );
        $Type = $this->rfkey_GetByteVal( $data, $offset + 3 );
        $Status = $this->rfkey_GetByteVal( $data, $offset + 4 );
        $PStatus = $this->rfkey_GetByteVal( $data, $offset + 5 );

        // first bit is relevant
        $ReaderActive = ( $Flags & 1 ) ? 1 : 0;

        switch ( $Type )
        {
            case 0x21: $TypeStr = "Relino"; break;
            case 0x23: $TypeStr = "Voxio"; break;
            case 0x26: $TypeStr = "Relino B"; break;
            case 0x16: $TypeStr = "rf:key Konverter"; break;
            default: $TypeStr = "Unbekannter Leser " . dechex( $Type ); break;
        }

        // first bit is relevant
        $DoorOpen = ( $Status & 1 ) ? 1 : 0;
        
        // second bit is relevant
        $Sabotage = ( $Status & 2 ) ? 1 : 0;

        // first bit is relevant
        $PeripheryError = ( $PStatus & 1 );
        
        return array(
            "Adress" => $Adress,
            "Status" => array(
                "Name" => '',
                "ReaderActive" => $ReaderActive,
                "ReaderType" => $Type,
                "ReaderTypeName" => $TypeStr,
                "DoorOpen" => $DoorOpen,
                "Sabotage" => $Sabotage,
                "PeripheryError" => $PeripheryError
                )
            );
    }
    
    /*
        convert rf:key reply code to text
    */
    private function rfkey_GetStatusText( $status )
    {
        switch( $status )
        {
            case -1: return "Kommando fehlgeschlagen"; break;
            case 0: return "Fehler bei der Verarbeitung oder fehlerhafter Datenblock"; break;
            case 1: return "Kommando erfolgreich ausgeführt"; break;
        }
    }

    /*
        copy reader name to internal reader status structure
        The name comes only with log messages in operations mode 0 or notification messages in mode 1 and 2
    */
    private function SetReaderName( $RDR_Adress, $RDR_Name )
    {
        $ReaderStatus = $this->GetReaderStatus();
        foreach ( $ReaderStatus as $key => $Reader )
        {
            if ( $RDR_Adress == $Reader[ "Adress" ] )
            {
                $ReaderStatus[ $key ][ "Status" ][ "Name" ] = $RDR_Name;
                break;
            }
        }
        $this->SetReaderStatus( $ReaderStatus );
    }

    /*
        copy reader name (if set) to internal reader status structure
    */
    private function SyncAndSaveReaderStatus( $NewReaderStatus )
    {
        // update name field if not empty
        $ReaderStatus = $this->GetReaderStatus();
        if ( !empty( $ReaderStatus ) )
        {
            foreach ( $NewReaderStatus as $key => $NewReader )
            {
                if ( '' == $NewReader[ "Status" ][ "Name" ] )
                foreach ( $ReaderStatus as $Reader )
                {
                    if ( $Reader[ "Adress" ] == $NewReader[ "Adress" ] )
                    {
                        $NewReaderStatus[ $key ][ "Status" ][ "Name" ] = $Reader[ "Status" ][ "Name" ];
                        break;
                    }
                }
            }
        }
        $this->SetReaderStatus( $NewReaderStatus );
    }

    /*
        rf:key protocol receiving handler
    */
    private function rfkey_ListenHandler( $data )
    {
        $ID = $this->rfkey_GetIntVal( $data );
        $Adress = $this->rfkey_GetIntVal( $data, 2 );
        switch( $ID )
        {
            case 0: // login
                $Status = $this->rfkey_GetIntVal( $data, 42 );
                $this->LogDebug( 'Login acknowledged - ' . $this->rfkey_GetStatusText( $Status ) );
                $this->SetLogonStatus( true );
                $this->rfkey_SetOperationsMode( $this->GetOperationsMode() );
                break;
            case 100: // heartbeat
                $this->rfkey_HandleHeartbeatStatus( $data );
                break;
            case 101: // relay/LED on
                $Status = $this->rfkey_GetIntVal( $data, 12 );
                $this->LogDebug( 'Relay/LED on acknowledged - ' . $this->rfkey_GetStatusText( $Status ) );
                if ( 1 == $Status )
                    $this->rfkey_HandleRelaisLEDOnACK( $Adress, $data );
                break;
            case 102: // relay/LED off
                $Status = $this->rfkey_GetIntVal( $data, 12 );
                $this->LogDebug( 'Relay/LED off acknowledged - ' . $this->rfkey_GetStatusText( $Status ) );
                if ( 1 == $Status )
                    $this->rfkey_HandleRelaisLEDOffACK( $Adress, $data );
                break;
            case 104: // operations mode
                $Status = $this->rfkey_GetIntVal( $data, 12 );
                $this->LogDebug( 'Operations mode acknowledged - ' . $this->rfkey_GetStatusText( $Status ) );
                $this->rfkey_HandleOperationsModeACK( $data );
                break;
            case 130: // external relay on/off
                $Status = $this->rfkey_GetIntVal( $data, 12 );
                $this->LogDebug( 'External relay on/off acknowledged - ' . $this->rfkey_GetStatusText( $Status ) );
                if ( 1 == $Status )
                    $this->rfkey_HandleExternalRelaisACK( $Adress, $data );
                break;
            case 140: // buzzer
                $Status = $this->rfkey_GetIntVal( $data, 12 );
                $this->LogDebug( 'Buzzer acknowledged - ' . $this->rfkey_GetStatusText( $Status ) );
                if ( 1 == $Status )
                    $this->rfkey_HandleBuzzer( $Adress, $data );
                break;
            case 200: // log entry
                $this->LogDebug( 'Log entry received' );
                $this->rfkey_HandleLogEntry( $data );
                break;
            case 201: // notification
                $this->LogDebug( 'Notification received' );
                $this->rfkey_HandleNotification( $data );
                break;
            default:
                $this->LogDebug( 'ID: ' . $ID );
                break;
        }
    }

    /*
        handle heartbeat containing status information
    */
    private function rfkey_HandleHeartbeatStatus( $data )
    {
        $ReaderStatus = array();
        $offset = 2;
        do
        {
            $Status = $this->rfkey_GetReaderStatus( $data, $offset );
            if ( empty( $Status ) )
                break;
            $ReaderStatus[] = $Status;
            // send to child reader instances
            $this->SendMessageToReaderInstances( $Status );
            $offset += 6;
        }
        while ( !empty( $Status ) );
        $this->SyncAndSaveReaderStatus( $ReaderStatus );
    }

    /*
        handle log entry information
    */
    private function rfkey_HandleLogEntry( $data )
    {
        /*

        Name			Typ		Größe (Bytes)
        ID				WORD 	2
        TYPE			WORD	2 <- BYTE 1!
        INFO 			WORD 	2 <- BYTE 1!
        DATETIME		BYTE	8
        RDR_ADRESS		WORD 	2
        RDR_NAME		CHAR	20
        TAG_UID			BYTE	11
        TAG_NAME		CHAR	20

        */

        $ID = $this->rfkey_GetIntVal( $data, 0 );
        $Type = $this->rfkey_GetByteVal( $data, 2 );
        $Info = $this->rfkey_GetByteVal( $data, 3 );
        $DateTime = $this->rfkey_GetDateTimeVal( $data, 4 );
        $RDR_Adress = $this->rfkey_GetIntVal( $data, 12 );
        $RDR_Name = utf8_encode( $this->rfkey_GetCharVal( $data, 14, 20 ) );
        $Tag_UID = $this->rfkey_GetUIDVal( $data, 34 );
        $Tag_Name = utf8_encode( $this->rfkey_GetCharVal( $data, 45, 20 ) );

        $this->SetReaderName( $RDR_Adress, $RDR_Name );
        
        if ( 2 == $Type )
        {
            $Text = 'Sabotage bei Leser ' . $RDR_Name . ', ID ' . dechex( $RDR_Adress );
            $LogMsg = array(
                "Adress" => $RDR_Adress,
                "Log" => array(
                    "ReaderName" => $RDR_Name,
                    "Text" => $Text
                    )
                );
            $this->SendMessageToReaderInstances( $LogMsg );
            $this->LogDebug( $Text );
            return;
        }
        if ( 1 == $Type )
        {
            $LogMsg = array(
                "Adress" => $RDR_Adress,
                "Log" => array(
                    "ReaderName" => $RDR_Name,
                    "TagUID" => $Tag_UID,
                    "TagName" => $Tag_Name,
                    "Access" => 0,
                    "Text" => ""
                    )
                );
            $this->LogDebug( 'Transponder erkannt bei Leser ' . $RDR_Name . ', ID ' . dechex( $RDR_Adress ) );
            switch ( $Info )
            {
                case 0:
                    $Text = 'Unbekannter Transponder, ID ' . $Tag_UID;
                    break;
                case 1:
                    $Text = 'Transponder ID ' . $Tag_UID . ' (' . $Tag_Name . ') nicht aktiviert';
                    break;
                case 2:
                    $Text ='Transponder ID ' . $Tag_UID . ' (' . $Tag_Name . ') Zutritt gewährt';
                    $LogMsg[ "Log" ][ "Access" ] = 1;
                    break;
                case 3:
                    $Text ='Transponder ID ' . $Tag_UID . ' (' . $Tag_Name . ') außerhalb berechtigter Zeit';
                    break;
                case 4:
                    $Text ='Transponder ID ' . $Tag_UID . ' (' . $Tag_Name . ') nicht berechtigt';
                    break;
                case 5:
                    $Text = 'Transponder ID ' . $Tag_UID . ' (' . $Tag_Name . ') an diesem Leser nicht berechtigt';
                    break;
                case 7:
                    $Text ='Transponder ID ' . $Tag_UID . ' (' . $Tag_Name . ') außerhalb der Gültigkeit';
                    break;
            }
            $LogMsg[ "Log" ][ "Text" ] = $Text;
            $this->LogDebug( json_encode( array_merge( array( "DataID" => RFKEY_TX), $LogMsg ) ) );
            $this->SendMessageToReaderInstances( $LogMsg );
        }
    }

    /*
        handle notification information
    */
    private function rfkey_HandleNotification( $data )
    {
        /*

        Name			Typ		Größe (Bytes)
        ID				WORD 	2
        TYPE			WORD	2 <- BYTE 1!
        DATETIME		BYTE	8
        RDR_ADRESS		WORD 	2
        RDR_NAME		CHAR	20
        TAG_UID			BYTE	11
        PIN_CODE		CHAR	20

        */

        $ID = $this->rfkey_GetIntVal( $data, 0 );
        $Type = $this->rfkey_GetByteVal( $data, 2 );
        $DateTime = $this->rfkey_GetDateTimeVal( $data, 3 );
        $RDR_Adress = $this->rfkey_GetIntVal( $data, 11 );
        $RDR_Name = utf8_encode( $this->rfkey_GetCharVal( $data, 13, 20 ) );
        $Tag_UID = $this->rfkey_GetUIDVal( $data, 33 );
        $PIN_Code = $this->rfkey_GetCharVal( $data, 44, 20 );

        $this->SetReaderName( $RDR_Adress, $RDR_Name );

        $NotificationMsg = array(
            "Adress" => $RDR_Adress,
            "Notification" => array(
                "ReaderName" => $RDR_Name,
                "TagUID" => $Tag_UID,
                "PINCode" => $PIN_Code
                )
            );
        
        $Text = '';
        $Notify = '';
        $Info = ' Leser ' . $RDR_Name . ', ID ' . dechex( $RDR_Adress );

        switch ( $Type )
        {
            case 1:
                // tag/transponder found
                $Notify = 'TAG';
                $Text = 'Transponder ID ' . $Tag_UID . ' erkannt an' . $Info;
                break;
            case 2:
                // sabotage found
                $Notify = 'SAB';
                $Text = 'Sabotage an' . $Info;
                break;
            case 3:
                // relay opened
                $Notify = 'ROP';
                $Text = 'Relais geöffnet für' . $Info;
                break;
            case 4:
                // relay closed
                $Notify = 'RCL';
                $Text = 'Relais geschlossen für' . $Info;
                break;
        }
        $NotificationMsg[ "Notification" ][ "Notify" ] = $Notify;
        $NotificationMsg[ "Notification" ][ "Text" ] = $Text;
        if ( '' != $Notify )
            $this->SendMessageToReaderInstances( $NotificationMsg );
        
    }

    /***********************************************************************
    
    * communications protocol from/to the rf:key system

    ************************************************************************/
    
    /*
        entry point for the periodic 5s timer
    */
    public function Heartbeat()
    {
        $Parent = $this->GetParentInstance();
        if ( 0 == $Parent )
            return;
        if ( $this->rfkey_IsSocketConnected( $Parent ) )
        {
            if ( $this->GetLogonStatus() )
                $this->rfkey_Heartbeat();
            else
                $this->rfkey_Logon();
        }
        else
            $this->SetLogonStatus( false );
    }
    
    /*
        assemble heartbeat package and send it
    */
    private function rfkey_Heartbeat()
    {
        // create data package
        $Data = chr( 100 ) . chr( 0x00 ); // command "Heartbeat"

        // send heartbeat
        $this->SendData( $Data );
    }

    /*
        log into the rf:key system
    */
    private function rfkey_Logon()
    {
        $Parent = $this->GetParentInstance();
        if ( 0 == $Parent )
            return false;

        // create data package
        $Data = chr( 0x00 ) . chr( 0x00 ); // command "Login"
        $Data .= str_pad( $this->GetUsername(), 20, chr( 0x00 ) ); // username
        $Data .= str_pad( $this->GetPassword(), 20, chr( 0x00 ) ); // password
        $Data .= chr( 0x00 ) . chr( 0x00 ); // reply

        // open connection
        $this->rfkey_Connect( $Parent );

        // send login
        $this->LogDebug( 'Logging on' );
        $this->SendData( $Data );
    }

    /*
        set the operations mode to
        0: normal/offline
        1: bus converter
        2: bus converter with backup (fallback to mode 0 on TCP/IP connection loss)
    */
    private function rfkey_SetOperationsMode( $OPMode = 0 )
    {
        $Parent = $this->GetParentInstance();
        if ( 0 == $Parent )
            return false;

        if ( ( 0 > $OPMode ) || ( 2 < $OPMode ) )
            return false;

        // create data package
        $Data = chr( 0x68 ) . chr( 0x00 ); // command "operations mode"
        $Data .= chr( $OPMode ) . chr( 0x00 ); // mode
        $Data .= chr( 0x00 ) . chr( 0x00 ); // reserved
        $Data .= chr( 0x00 ) . chr( 0x00 ); // reserved
        $Data .= chr( 0x00 ) . chr( 0x00 ); // reserved
        $Data .= chr( 0x00 ) . chr( 0x00 ); // reserved
        $Data .= chr( 0x00 ) . chr( 0x00 ); // reply

        $this->SendData( $Data );
    }

    /*
        assemble buzz package and send it
    */
    private function Buzz( $RDR_Adress, $Duration = RFKEY_Default_Buzz_Time_hms )
    {
        if ( !$this->GetLogonStatus() )
            return false;

        // create data package
        $Data = chr( 0x8c ) . chr( 0x00 ); // command 140 - "Buzzzzzzz"
        $Data .= chr( $RDR_Adress ^ 0xff00 ) . chr( $RDR_Adress >> 8 ); // adress
        $Data .= chr( $Duration ^ 0xff00 ) . chr( $Duration >> 8 ); // time in 100ms
        $Data .= chr( 0x20 ) . chr( 0x00 ); // frequency
        $Data .= chr( 0x00 ) . chr( 0x00 ); // reserved
        $Data .= chr( 0x00 ) . chr( 0x00 ); // reserved

        $this->SendData( $Data );
    }

    /*
        assemble open/close external relay package and send it
    */
    private function OpenCloseRelay( $RelayNumber, $Mode, $Duration = RFKEY_Default_Relais_Time_hms )
    {
        if ( !$this->GetLogonStatus() )
            return false;

        $MaxRelayCount = $this->GetRelayExtensionCount() * 4;
        $MaxOpenTime = $this->GetRelayMax() * 10;
        $DefaultOpenTime = $this->GetRelayDefault() * 10;
        
        if ( ( 0 > $DoorRelais ) || ( $MaxRelayCount < $DoorRelais ) )
            return false;

        if ( RFKEY_Max_Duration_hms < $OpenTime )
            $OpenTime = RFKEY_Max_Duration_hms;
        if ( $MaxOpenTime < $OpenTime )
            $OpenTime = $MaxOpenTime;
        if ( 0 == $OpenTime )
            $OpenTime = $DefaultOpenTime;

        if ( $Mode )
            $OnOff = chr( 0x01 ); // 1=on
        else
            $OnOff = chr( 0x00 ); // 0=off

        // create data package
        $Data = chr( 0x82 ) . chr( 0x00 ); // command 130 - "switch external relay"
        $Data .= chr( $RelayNumber + 3 ) . chr( 0x00 ); // relay
        $Data .= $OnOff . chr( 0x00 );
        $Data .= chr( $Duration ^ 0xff00 ) . chr( $Duration >> 8 ); // time in 100ms
        $Data .= chr( 0x00 ) . chr( 0x00 ); // reserved
        $Data .= chr( 0x00 ) . chr( 0x00 ); // reserved
        
        $this->SendData( $Data );
    }

    private function OpenLocalRelay( $RDR_Adress, $Duration = RFKEY_Default_Relais_Time_hms )
    {
        $this->rfkey_LocalRelayorLED( $RDR_Adress, 0x00, true, $Duration );
    }

    private function CloseLocalRelay( $RDR_Adress )
    {
        $this->rfkey_LocalRelayorLED( $RDR_Adress, 0x00, false );
    }

    private function LocalLEDOn( $RDR_Adress, $Duration = RFKEY_Default_Relais_Time_hms )
    {
        $this->rfkey_LocalRelayorLED( $RDR_Adress, 0x01, true, $Duration );
    }

    private function LocalLEDOff( $RDR_Adress )
    {
        $this->rfkey_LocalRelayorLED( $RDR_Adress, 0x01, false );
    }

    /*
        assemble local relay/LED on package and send it
    */
    private function rfkey_LocalRelayorLED( $RDR_Adress, $RelaisLEDByte, $Mode, $Duration = RFKEY_Default_Relais_Time_hms )
    {
        if ( !$this->GetLogonStatus() )
            return false;

        if ( $Mode )
            $Command = chr( 0x65 ); // command 101 - "relay/LED on"
        else
            $Command = chr( 0x66 ); // command 102 - "relay/LED off"

        // create data package
        $Data = $Command . chr( 0x00 );
        $Data .= chr( $RDR_Adress ^ 0xff00 ) . chr( $RDR_Adress >> 8 ); // adress
        $Data .= chr( $Duration ^ 0xff00 ) . chr( $Duration >> 8 ); // time in 100ms
        $Data .= chr( $RelaisLEDByte ) . chr( 0x00 ); // relay/LED
        $Data .= chr( 0x00 ) . chr( 0x00 ); // reserved
        $Data .= chr( 0x00 ) . chr( 0x00 ); // reserved
        
        $this->SendData( $Data );
    }


    /***********************************************************************
    
    * methods for script access

    ************************************************************************/

    /*
        returns the internal reader status structure
    */
    public function GetCardReaderStatus()
    {
        return $this->GetReaderStatus();
    }
    /*
        open external relay - proxy function for OpenCloseRelay
    */
    public function OpenRelay( $RelayNumber, $Duration = RFKEY_Default_Relais_Time_hms )
    {
        return $this->OpenCloseRelay( $RelayNumber, true, $Duration );
    }

    /*
        close external relay - proxy function for OpenCloseRelay
    */
    public function CloseRelay( $RelayNumber )
    {
        return $this->OpenCloseRelay( $RelayNumber, false );
    }


}

?>

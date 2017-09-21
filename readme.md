rf:key Module für IP-Symcon
===

Mit dieser Bibliothek kann die [rf:key RFID Zutrittskontrolle](http://www.rf-key.de) in IP Symcon eingebunden werden.
Warum augerechnet rf:key? Das System hat m.E. gegenüber anderen Systemen am Markt einen entscheidenden Vorteil: RFID-Zugangskarten können kopiert werden, die gängigen Kartenverschlüsselungen sind bereits gebrochen. Bei rf:key mit der HSEC-Erweiterung wird für jedes System ein eigener Schlüssel verwendet, welcher hardwareseitig in einem nicht auslesbaren Speicher abgelegt wurde. Die Authentifizierung erfolgt mit DESfire-Karten über ein Challenge-Response-Verfahren. Diese Kombination macht das System so sicher.

Der rf:key Controller stellt eine brauchbare Verwaltung über ein Web-Interface zur Verfügung. Für maximale Flexibilität lag es nahe, das System in IP Symcon einzubinden. Hier ist nun das Modul dazu ;-)

**Inhaltverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

Das rf:key-System kann im Offline-Modus, als Buskonverter oder als Buskonverter mit Fallback zum Oflline-Modus (bei Ausfall von IP Symcon) betrieben werden. Die Betriebsart ist im Gateway-Modul auswählbar.

1. Alle Betriebsmodi
* Kartenleser
  * Anzeige des rf:key Kartenleser-Namens
  * Visualisierung und Steuerung von
    * Zugeordnetem Tür-Relais
    * LED und Buzzer
  * Visualisierung von
    * Sabotage
    * Status Betriebsbereit/Ausgefallen
  * Zugriff auf
    * Letzte gelesene Transponder-ID
    * Logfile (wird über IP Symcon Systemneustarts hinweg gespeichert)
* Relais-Erweiterung
  * Steuerung der Relais

2. Offline-Modus
* Kartenleser
  * Anzeige der letzten erfolgreich autorisierten Transponder-ID und des rf:key-Transpondernamens

### 2. Voraussetzungen

- IP-Symcon ab Version 4.x
- rf:key-System in beliebiger Ausbaustufe
- Einen Benutzer im rf:key-System mit der Berechtigung **"T"**

### 3. Software-Installation

Über das Modul-Control folgende URL hinzufügen.
`git://github.com/skyslasher/rf-key.git`

### 4. Einrichten der Instanzen in IP-Symcon

Unter "Instanz hinzufügen" zuerst den **rfkey Konfigurator** hinzufügen. Dieser ist unter dem Hersteller **(Konfigurator)** aufgeführt. Hierbei wird zusätzlich eine **Client Socket**-I/O-Instanz und eine **rfkey Gateway**-Splitter-Instanz angelegt.
Die 'Client-Socket'-Instanz ist nicht direkt konfigurierbar, sie wird über die Konfigurationsseite des rf-key Gateways konfiguriert.

Zuerst wird das **rfkey Gateway** eingerichtet:

__Konfigurationsseite__:

Name                              | Beschreibung
--------------------------------- | ---------------------------------
Aktiv                             | Aktivieren/Deaktivieren der Verbindung zum rf:key-System
Username                          | Benutzer im rf:key-System mit der Berechtigung "T"
Passwort                          | Passwort dieses Benutzers
Relaiserweiterungen               | Anzahl der Relaiserweiterungen am rf:key-Bus
Betriebsart                       | Betriebsart wie in Abschnitt 1 beschrieben
Default-Öffnungszeiten für Relais | 
Standard (s)                      | Öffnungszeit falls der Funktion keine Zeit übergeben wurde
Maximum (s)                       | Maximale Öffnungszeit

Sobald die Verbindung zum rf:key-System hergestellt wurde, können die Kartenleser eingerichtet werden. Diese werden automatisch erkannt. Damit die in der rf:key-Verwaltungsoberfläche eingetragenen Namen der Kartenleser übernommen werden können, bitte nun an jedem Kartenleser einen Lesevorgang durchführen.

Über das Konfigurator-Modul können die Kartenleser-Instanzen **rfkey Reader** nun komfortabel angelegt werden. Hierfür den gewünschten Leser in der Tabelle auswählen und auf den Button "Instanz erstellen" klicken. Die Instanz wird nun erzeugt, mit Standardwerten konfiguriert und mit dem rfkey Gateway verbunden.

Die **rfkey Reader** haben folgende Konfigurationsoptionen:

__Konfigurationsseite__:

Name                                  | Beschreibung
------------------------------------- | ---------------------------------
Leser-Adresse (HEX)                   | Wird vom Konfigurator ausgefüllt
Öffnungszeiten für Relais             | 
Standard (s)                          | Öffnungszeit falls der Funktion keine Zeit übergeben wurde oder diese aus der Visualisierung angestoßen wurde
Maximum (s)                           | Maximale Öffnungszeit
Standardzeit für Summer (Sekunden)    | Standardzeit falls der Funktion keine Zeit übergeben wurde oder diese aus der Visualisierung angestoßen wurde
Maximale Summer-Zeit (Sekunden)       | Maximale Summer-Zeit
Aufzeichnung der (versuchten) Zugänge | Maximale Länge des Zugangslogs


### 5. Statusvariablen und Profile

Die Statusvariablen werden automatisch angelegt. Das Löschen auch einzelner Variablen kann zu Fehlfunktionen führen.


#### Statusvariablen

Die **rfkey Reader**-Instanzen haben folgende Statusvariablen:

Name                               | Typ     | Beschreibung
---------------------------------- | ------- | ----------------
Tür                                | Boolean | Status des zugeordneten Türrelais, über WebFront schaltbar
Rote LED                           | Boolean | Status der roten LED, über WebFront schaltbar
Summer                             | Boolean | Status des Summers, über WebFront schaltbar
Aktiv                              | Boolean | Status Betriebsbereit/Ausgefallen
Sabotagekontakt                    | Boolean | Status Sabotage
Letzte autorisierte Transponder-ID | String  | Transponder-ID (nur bei Betriebsmodus 1)
Letzter autorisierter Transponder  | String  | Transponder-Name aus rf:key (nur bei Betriebsmodus 1)
Letzte Transponder-ID              | String  | Transponder-ID
Letzter Transponder                | String  | Transponder-Name aus rf:key (nur bei Betriebsmodus 1)
Letzter PIN Code                   | String  | Falls ein Leser mit PIN_Code verwendet wird erscheint hier der zusätzlich zum gelesenen Transponder eingegebene Code


#### Profile:

Es werden keine Variablenprofile angelegt.


### 6. WebFront

Die Statusvariablen sind mir Profilen für das WebFront vorbereitet. Tür, Rote LED und Summer lassen sich über das WebFront bedienen.


### 7. PHP-Befehlsreferenz

#### rfkey Gateway

`void RFKEY_OpenRelay(integer $InstanceID, integer $RelayNumber, integer $Duration = 0);`   
Öffnet über das Gateway mit der InstanzID `$InstanzID` das Relais `$RelayNumber` (0=E0, 1=E1, ...) für den Zeitraum `$Duration` (in 100ms. 0 = Standardzeit des Relais, Default-Wert falls nicht übergeben).
Die Funktion liefert keinerlei Rückgabewert.  

`void RFKEY_CloseRelay(integer $InstanceID, integer $RelayNumber);`   
Schließt über das Gateway mit der InstanzID `$InstanzID` das Relais `$RelayNumber` (0=E0, 1=E1, ...).
Die Funktion liefert keinerlei Rückgabewert.  

`json_string RFKEY_GetCardReaderStatus(integer $InstanceID);`   
Gibt den internen Status-Array der an das Gateway mit der InstanzID `$InstanzID` angeschlossenen Kartenleser als JSON-codierten String zurück. Der Status wird bei aktiver Verbindung alle 5 Sekunden aktualisiert.


#### rfkey Reader

`void RFKEY_OpenDoorRelay(integer $InstanceID, integer $OpenTime = 0);`   
Öffnet das dem Kartenleser mit der InstanzID `$InstanzID` zugeordnete Relais mit der Standard-Öffnungszeit des Relais für den Zeitraum `$OpenTime` (in 100ms. 0 = Standardzeit des Relais, Default-Wert falls nicht übergeben).
Die Funktion liefert keinerlei Rückgabewert.  

`void RFKEY_OpenDoorRelayDefault(integer $InstanceID);`   
Öffnet das dem Kartenleser mit der InstanzID `$InstanzID` zugeordnete Relais mit der Standard-Öffnungszeit des Relais.
Die Funktion liefert keinerlei Rückgabewert.  

`void RFKEY_CloseDoorRelay(integer $InstanceID);`   
Schließt das dem Kartenleser mit der InstanzID `$InstanzID` zugeordnete Relais.
Die Funktion liefert keinerlei Rückgabewert.  

`void RFKEY_SwitchBuzzer(integer $InstanceID, boolean $State, integer $BuzzTime = 0);`   
Schalten den Summer des Kartenlesers mit der InstanzID `$InstanzID` auf den Wert `$State` (true = An; false = Aus) für den Zeitraum `$BuzzTime` (in 100ms. 0 = Standardzeit für Summer, Default-Wert falls nicht übergeben).
Die Funktion liefert keinerlei Rückgabewert.  

`void RFKEY_SwitchLED(integer $InstanceID, boolean $State, integer $Duration = 0);`   
Setzt den Status der roten LED des Kartenlesers mit der InstanzID `$InstanzID`  auf den Wert `$State` (true = An; false = Aus) für den Zeitraum `$Duration` (in 100ms. 0 = unbegrenzt, Default-Wert falls nicht übergeben).
Die Funktion liefert keinerlei Rückgabewert.  

`string RFKEY_GetTransponderLog(integer $InstanceID);`   
Gibt das Transponder-Log des Kartenlesers mit der InstanzID `$InstanzID` als String mit Zeilenumbrüchen zurück.

`void RFKEY_ClearTransponderLog(integer $InstanceID);`   
Löscht das Transponder-Log des Kartenlesers mit der InstanzID `$InstanzID`.
Die Funktion liefert keinerlei Rückgabewert.  


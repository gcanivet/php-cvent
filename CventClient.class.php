<?php
/*
** CventClient.class.php
** Author: github.com/gcanivet
** Description: A php soap client for the Cvent API. 
**
** To Do:
**     - add additional remote functions support
**
*/
ini_set('soap.wsdl_cache_enabled', 1); 
ini_set('soap.wsdl_cache_ttl', 86400); //default: 86400, in seconds

class SoapDebugClient extends SoapClient {
	public function __doRequest($request, $location, $action, $version, $one_way = 0) {
		//if (DEBUG) print htmlspecialchars($request);
		flush();
		return parent::__doRequest($request, $location, $action, $version);
	}	
}

class CventClient extends SoapClient {
	public $client;
	public $ServerURL;
	public $CventSessionHeader;
	public $debug = false;
	public $MAX_FILTER_SIZE = 100;# 256 doesn't seem to work.
	public $RESULTS_PER_PAGE = 200;
	
	public function CventClient() {
	}

	public function Login($acct, $username, $password) {
		$this->client = new SoapDebugClient("https://api.cvent.com/soap/V200611.ASMX?WSDL", array('trace' => true, 'exceptions' => true));
		$cache = true;
		$file = 'cvent_api_session.txt';
		if($cache && file_exists($file) && time() <= strtotime("+1 hour", $last = filemtime($file))) { 
			# a valid session is cached, use it
			if($this->debug) print 'CventClient::Session already exists.<br/>';
			$data = file_get_contents($file);
			$arr_response = array();
			$arr_response = explode(',', $data);
			$this->ServerURL = $arr_response[0];
			$this->CventSessionHeader = $arr_response[1];					
		} else { # sessions expire 1hr after CREATION
			if($this->debug) print 'CventClient::Session does not exist. Get new one.<br/>';
			$params = array();
			$params['AccountNumber'] = $acct;
			$params['UserName']		= $username;
			$params['Password']		= $password;
			
			$response = $this->client->Login($params);
			if(!$response->LoginResult->LoginSuccess) {
				throw new Exception("CventClient:: Login unsuccessful");
			}
			$arr_response = array();
			$arr_response[] = $response->LoginResult->ServerURL;
			$arr_response[] = $response->LoginResult->CventSessionHeader;
			$data = implode(',', $arr_response);
			file_put_contents($file, $data);
			$this->ServerURL = $arr_response[0];
			$this->CventSessionHeader = $arr_response[1];
		}
		$this->client->__setLocation($this->ServerURL);
		$header_body = array('CventSessionValue' => $this->CventSessionHeader);
		$header = new SoapHeader('http://api.cvent.com/2006-11', 'CventSessionHeader', $header_body);
		$this->client->__setSoapHeaders($header);
		if($this->debug) print 'CventClient:: ServerURL: '.$this->ServerURL.', CventSessionHeader: '.$this->CventSessionHeader.'<br/>';
	}
	
	public function DescribeGlobal() {
		print "CventClient::DescribeGlobal:<br/>";
		$response = $this->client->DescribeGlobal();
		print "<pre>";
		print_r($response);
		print "</pre>";
	}			
	
	public function GetEventById($eventId) {
		# note: typicaly a limit of 25,000 ids returned;
		# note: maximum 256 search filters
		$events = $this->RetrieveEvents($eventId);	
		if(sizeof($events) != 1) throw new Exception('CventClient::GetEventById: EventId '.$eventId.' not found');
		return $events[0];
	}
	
	public function GetUpcomingEvents() {
		$criteria->ObjectType = 'Event';
		$criteria->CvSearchObject->SearchType = 'AndSearch';
		$criteria->CvSearchObject->Filter[0]->Field = 'EventStartDate';
		$criteria->CvSearchObject->Filter[0]->Operator = 'Greater than';
		$criteria->CvSearchObject->Filter[0]->Value = date('Y-m-d', strtotime('-14 days')).'T00:00:00'; // '2011-10-31T00:00:00';
		$response = $this->client->Search($criteria);
		if(isset($response->SearchResult->Id)) return $response->SearchResult->Id;
		return false;
	}
	
	public function GetNumberOfRegistrations($eventId) {
		$criteria->ObjectType = 'Registration';
		$criteria->CvSearchObject->Filter[0]->Field = 'EventId';
		$criteria->CvSearchObject->Filter[0]->Operator = 'Equals';
		$criteria->CvSearchObject->Filter[0]->Value = $eventId;
		$response = $this->client->Search($criteria);
		if(isset($response->SearchResult->Id)) return count($response->SearchResult->Id);
		return false;
	}
	
	public function GetNumberOfGuests($eventId) {
		$criteria->ObjectType = 'Guest';
		$criteria->CvSearchObject->Filter[0]->Field = 'EventId';
		$criteria->CvSearchObject->Filter[0]->Operator = 'Equals';
		$criteria->CvSearchObject->Filter[0]->Value = $eventId;
		$response = $this->client->Search($criteria);
		if(isset($response->SearchResult->Id)) return count($response->SearchResult->Id);
		return false;
	}
	
	public function GetAllDistributionLists() {
		// needs to be tested
		$criteria->ObjectType = 'DistributionList';
		$criteria->CvSearchObject->SearchType = 'OrSearch';
		$criteria->CvSearchObject->Filter[0]->Field = 'DistributionListName';
		$criteria->CvSearchObject->Filter[0]->Operator = 'Equals';
		$criteria->CvSearchObject->Filter[0]->Value = 'Something';
		$criteria->CvSearchObject->Filter[1]->Field = 'DistributionListName';
		$criteria->CvSearchObject->Filter[1]->Operator = 'Not Equal to';
		$criteria->CvSearchObject->Filter[1]->Value = 'Something';
		$response = $this->client->Search($criteria);
		if(isset($response->SearchResult->Id)) return $response->SearchResult->Id;
		return false;
	}
	
	public function SearchContactBySourceId($remaxId) {
		$criteria->ObjectType = 'Contact';
		$criteria->CvSearchObject->SearchType = 'AndSearch';
		$criteria->CvSearchObject->Filter[0]->Field = 'SourceId';
		$criteria->CvSearchObject->Filter[0]->Operator = 'Equals';
		$criteria->CvSearchObject->Filter[0]->Value = $remaxId;
		$response = $this->client->Search($criteria);
		if(isset($response->SearchResult->Id)) return $response->SearchResult->Id;
		return false;
	}
	
	public function SearchContactsByGroupId($groupId) {
		$criteria->ObjectType = 'Contact';
		$criteria->CvSearchObject->SearchType = 'AndSearch';
		$criteria->CvSearchObject->Filter[0]->Field = 'GroupId';
		$criteria->CvSearchObject->Filter[0]->Operator = 'Equals';
		$criteria->CvSearchObject->Filter[0]->Value = $groupId;
		$response = $this->client->Search($criteria);
		return $response;
	}
	
	private function RetrieveAllPages($objecttype, $ids) {
		if(!is_array($ids)) $ids = array($ids); // safety measure
		$results = array();
		for($i=0; $i < count($ids); $i += $this->RESULTS_PER_PAGE) {
			if ($this->debug) print "CventClient::RetrievePages:: retrieving $objecttype using Ids from $i to ".($i+$this->RESULTS_PER_PAGE)."<br/>";
			$batch = array_slice($ids, $i, $i + $this->RESULTS_PER_PAGE);
			$criteria = NULL;
			$criteria->ObjectType = $objecttype;
			$criteria->Ids = $batch;
			$tmp = $this->client->Retrieve($criteria);
			if(is_array($tmp->RetrieveResult->CvObject)) {
				$results = array_merge($results, $tmp->RetrieveResult->CvObject);
			} else {
				$results = array_merge($results, array($tmp->RetrieveResult->CvObject));
			}
		}
		return $results;
	}

	public function RetrieveEvents($eventIds) {
		return $this->RetrieveAllPages('Event', $eventIds);
	}

	public function RetrieveContacts($contactIds) {
		return $this->RetrieveAllPages('Contact', $contactIds);
	}
	public function RetrieveDistributionLists($dIds) {
	}
	
	public function RetrieveContactBySourceId($remaxid) {
		$result = $this->SearchContactBySourceId($remaxid);		
		if($result === false) throw new Exception("CventClient::RetrieveContactBySourceId::$remaxid not found, cannot retrieve");
		$ids[] = $result->SearchResult->Id;
		$result = $this->RetrieveContacts($ids);		
		return $result;
	}
	
	public function RetrieveContactIdsBySourceIds($sourceIds) {
		// needs to be tested
		$total = count($sourceIds);
		$contactIds = array();
		for($i = 0; $i < count($sourceIds); $i += $this->MAX_FILTER_SIZE) {
			if ($this->debug) print "CventClient::RetrieveContactIdsBySourceIds:: retrieving contactIds from $i to ".($i + $this->MAX_FILTER_SIZE)."<br/>";
			$batch = array_slice($sourceIds, $i, $i + $this->MAX_FILTER_SIZE);		
			$criteria = NULL;
			$criteria->ObjectType = 'Contact';
			$criteria->CvSearchObject->SearchType = 'OrSearch';	
			for($j=0; $j < sizeof($batch); $j++) {
				$criteria->CvSearchObject->Filter[$j]->Field = 'SourceId';
				$criteria->CvSearchObject->Filter[$j]->Operator = 'Equals';
				$criteria->CvSearchObject->Filter[$j]->Value = $batch[$j];
			}
			$tmp = $this->client->Search($criteria);
			$tmp = $tmp->SearchResult->Id;
			if(is_array($tmp)) {
				$contactIds = array_merge($contactIds, $tmp);
			} else {
				$contactIds = array_merge($contactIds, array($tmp));
			}
		}
		return $contactIds;			
	}
	
	
	public function CreateUpdateContacts($type, $contacts) {
		# type = 'Create' or 'Update'
		$total = sizeof($contacts);
		$pages = ceil($total / $this->RESULTS_PER_PAGE);
		$remainder = $total % $this->RESULTS_PER_PAGE;
		$passed = array();
		$failed = array();
		for($i = 0; $i < $pages; $i++) {
			$x = $i * $this->RESULTS_PER_PAGE;
			$y = $this->RESULTS_PER_PAGE;
			if($pages == $i + 1) $y = $remainder;
			if ($this->debug) print "CventClient::CreateUpdateContacts::Page $i, creating $y Ids starting at array index $x<br/>";
			$batch = array_slice($contacts, $x, $y);		
			$criteria =  NULL;
			$tmp = NULL;
			$criteria->Contacts = $batch;		
			# process batch
			if($type == 'Create') {
				$tmp = $this->client->CreateContact($criteria);
				$tmp = @$tmp->CreateContactResult->CreateContactResult;
			} elseif ($type == 'Update') {
				$tmp = $this->client->UpdateContact($criteria);
				$tmp = @$tmp->UpdateContactResult->UpdateContactResult;
			} else {
				throw new Exception("CventClient::CreateUpdateContacts:: Invalid $type for this method");
			}
			if(isset($tmp)) {
				if(is_array($tmp)) {
					$result = $tmp;				
				} else {
					$result = array($tmp);
				}
				if(sizeof($result) != sizeof($batch)) throw new Exception("CventClient::CreateUpdateContacts:: Size of results mismatch with size of batch");
				# organize pass/fails
				$pass = array();
				$fail = array();
				for($j = 0; $j < sizeof($result); $j++) {
					if(isset($result[$j]->Errors->Error)) {
						$fail[] = array('contact' => $batch[$j], 'result' => $result[$j]);
					} else {
						$pass[] = array('contact' => $batch[$j], 'result' => $result[$j]);
					}
				}
				$failed = array_merge($failed, $fail);
				$passed = array_merge($passed, $pass);				
			} 
		}
		if(sizeof($passed) + sizeof($failed) != $total) throw new Exception("CventClient::CreateUpdateContacts:: Total pass+fails does not match total contacts.");
		return array('passed' => $passed, 'failed' => $failed); 
	}
		
	# utilities		
	public function webServiceDetails() {
		print "<strong>Functions:</strong><pre>";
		print_r($this->client->__getFunctions());
		print "</pre>";
		
		print "<strong>Types:</strong><pre>";
		print_r($this->client->__getTypes());
		print "</pre>";
	}
	public function debug() {
		print "<pre>";
		print htmlspecialchars($this->client->__getLastRequestHeaders());
		print "</pre>";
		print "<pre>";		
		print htmlspecialchars($this->client->__getLastRequest());
		print "</pre>";
		print "<pre>";
		print htmlspecialchars($this->client->__getLastResponseHeaders());
		print "</pre>";
		print "<pre>";
		print htmlspecialchars($this->client->__getLastResponse());
		print "</pre>";
	}
}
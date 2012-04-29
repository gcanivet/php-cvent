CventClient
===========

A soap client for the Cvent Event Registration API

How to use
----------
	require('CventClient.class.php')
	$cc = new CventClient();
	$cc->Login($account, $username, $password);
	$events = $cc->GetUpcomingEvents();
	print_r($events);

License
-------
<a rel="license" href="http://creativecommons.org/licenses/by/3.0/"><img alt="Creative Commons License" style="border-width:0" src="http://i.creativecommons.org/l/by/3.0/88x31.png" /></a><br />This work is licensed under a <a rel="license" href="http://creativecommons.org/licenses/by/3.0/">Creative Commons Attribution 3.0 Unported License</a>.

<?php
require '../vendor/autoload.php';
session_start();

// loading config.json
$config = json_decode(file_get_contents(dirname(__FILE__)."/../_config.json"),false);
Flight::set("config",$config);



Flight::route('/', function() {
    Flight::view()->set('auth', $_SESSION["login"] ?? false);
    Flight::view()->set('loadingEndpoint', Flight::get("config")->loadingEndpoint);
    Flight::render("template.php");
});

Flight::route('/login', function() 
{
	//var_dump(Flight::request()->data->pass);
    //var_dump(Flight::get("config")->webPassword);
    
    $_SESSION["login"] = (Flight::request()->data->pass === Flight::get("config")->webPassword);
    
    if($_SESSION["login"]) Flight::redirect('/');
    
    Flight::view()->set('auth', $_SESSION["login"] ?? false);
    Flight::view()->set('error', "wrong password, try again");
    Flight::render("template.php");
});

Flight::route('/loadEvents', function() {
    if(!$_SESSION["login"]) Flight::redirect('/login');
    
    $database = new \JamesMoss\Flywheel\Config(dirname(__FILE__) . '/../DB');
    $repo = new \JamesMoss\Flywheel\Repository('events', $database);

    /** @var \JamesMoss\Flywheel\Result $events */
    $events = $repo->query()->execute();
	//var_dump($events);
	
	Flight::json($events->getIterator()->getArrayCopy());
});
Flight::route('/syncEvent', function() {
    if(Flight::get("config")->customToken !== Flight::request()->query['token']) die("no");
    
    $payload = Flight::request()->data->getData();
    var_dump($payload);
    
    //file_put_contents(dirname(__FILE__)."/../DB/payload.json", print_r($payload,true));

	$activity = $payload[0]["value"];
    $date = date_create_from_format('H:i T d/m',$payload[1]["value"]);
    $start = date_format($date,"Y-m-d H:i:s");
    date_add($date,date_interval_create_from_date_string('1 hour'));
    $end = date_format($date,"Y-m-d H:i:s");
	$id = $payload[2]["value"];
    $description = $payload[3]["value"];
    $guardians = explode(", ",$payload[4]["value"]);
    if(count($payload) == 6) foreach ( explode(", ",$payload[5]["value"]) as $alt) $guardians[] = "(".$alt.")";
        
    $database = new \JamesMoss\Flywheel\Config(dirname(__FILE__) . '/../DB');
    $repo = new \JamesMoss\Flywheel\Repository('events', $database);

    if ($post = $repo->findById($id)) // $post = $repo->findById($payload[2]["value"])
    {
        $post->id = $id;
        $post->calendardId = '1';
        $post->title = $activity;
        $post->location = $activity;
        $post->body = $description;
        $post->start = $start;
        //$post->end = $end;
        $post->category = 'time';
        $post->attendees = $guardians;
        $post->isReadOnly = true;

        $repo->update($post);

		print "updated";
    }
    else
    {
    	$post = new \JamesMoss\Flywheel\Document(array(
        	'id' => $id,
            'calendardId' => '1',
            'title' => $activity,
            'location' => $activity,
            'body' => $description,
            'start' => $start,
            //'end' => $end,
            'category' => 'time',
            'attendees' => $guardians,
            'isReadOnly' => true
        ));
        $post->setId($id);

        $repo->store($post);

        print "added";
    }
    Flight::stop();
});
Flight::route('/deleteEvent', function() {
    if(Flight::get("config")->customToken !== Flight::request()->query['token']) die("no");
    
	preg_match('/\d+/',Flight::request()->data->event,$matches);
    $id = $matches[0];

    $database = new \JamesMoss\Flywheel\Config(dirname(__FILE__) . '/../DB');
    $repo = new \JamesMoss\Flywheel\Repository('events', $database);

    if ($post = $repo->findById($id)) $repo->delete($id);

    print "cancelled";
    Flight::stop();
});
Flight::start();
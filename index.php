<?php

date_default_timezone_set('America/Chicago');
ini_set('display_errors',1);
error_reporting(E_ALL);

$times = new TransitTimes();
$times->index();

class TransitTimes {

    public static $API_URLS = array(
        'alerts'        => 'http://www.transitchicago.com/api/2.0/',
        'bus'           => 'http://www.ctabustracker.com/bustime/api/v2/',
        'train'         => 'http://lapi.transitchicago.com/api/1.0/',
        'trainStops'    => 'http://data.cityofchicago.org/resource/8mj8-j3c4.json'
    );

    public static $API_ENDPOINTS = array(
        'alerts' => array(
            'routes'            => 'routes.aspx',
            'alerts'            => 'alerts.aspx'
        ),
        'bus' => array(
            'time'              => 'gettime',
            'vehicles'          => 'getvehicles',
            'routes'            => 'getroutes',
            'routeDirections'   => 'getdirections',
            'stops'             => 'getstops',
            'patterns'          => 'getpatterns',
            'predictions'       => 'getpredictions',
            'serviceBulletins'  => 'getservicebulletins'
        ),
        'train' => array(
            'arrivals'          => 'ttarrivals.aspx',
            'followThisTrain'   => 'ttfollow.aspx',
            'locations'         => 'ttpositions.aspx'
        )
    );

    public static $TRAIN_LINES = array(
        'Red'   => array(
            'niceName'      => 'Red Ln',
            'trainStopsId'  => 'red'
        ),
        'Blue'  => array(
            'niceName'      => 'Blue Ln',
            'trainStopsId'  => 'blue'
        ),
        'Brn'   => array(
            'niceName'      => 'Brown Ln',
            'trainStopsId'  => 'brn'
        ),
        'G'     => array(
            'niceName'      => 'Green Ln',
            'trainStopsId'  => 'g'
        ),
        'Org'   => array(
            'niceName'      => 'Orange Ln',
            'trainStopsId'  => 'o'
        ),
        'P'     => array(
            'niceName'      => 'Purple Ln',
            'trainStopsId'  => 'p'
        ),
        'Pexp'  => array(
            'niceName'      => 'Purple Ln Exp',
            'trainStopsId'  => 'pexp'
        ),
        'Pink'  => array(
            'niceName'      => 'Pink Ln',
            'trainStopsId'  => 'pnk'
        ),
        'Y'     => array(
            'niceName'      => 'Yellow Line',
            'trainStopsId'  => 'y'
        )
    );

    function __construct(){

    	require(__DIR__.'/keys.php');
    	$this->apiKeys = array(
    		'bus' => $BusKey,
    		'train' => $TrainKey,
    	);

    }

    function curlCall($api, $endPoint, $params=array()){
		
		$params['key'] = $this->apiKeys[$api];
		switch($api){
			case 'bus':
				$params['format'] = 'json';
				break;
			case 'train':
				$params['outputType'] = 'JSON';
				break;
		}

		$curlUrl = self::$API_URLS[$api].'/'.self::$API_ENDPOINTS[$api][$endPoint] .'?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$curlUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if(FALSE === $curlResponse = curl_exec ($ch)){
            throw new Exception('Curl Error #'.curl_errno($ch).' - '.curl_error($ch));
        }
        curl_close ($ch);

        if(NULL === $jsonResponse = json_decode($curlResponse, true)){
            throw new Exception('Invalid JSON returned! '.$curlResponse);
        }
        return $jsonResponse;
    }

	function index(){

		list($usec, $sec) = explode(" ", microtime());
		$usec = round( $usec * 1000 );
		$traceFile = new DateTime();
		$traceFile = $traceFile->format('Y-m-d_H-i-s_').str_pad($usec, 4, '0', STR_PAD_LEFT);
		$traceFilePath = "apidata/";

		//GET BUS TIMES:
		//get CTA time
		//http://www.ctabustracker.com/bustime/api/v2/gettime?key=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx&format=json
		//$response = $this->curlCall('bus','time');
		//var_dump($response);

		//get predictions for all stops
		//http://www.ctabustracker.com/bustime/api/v2/getpredictions?format=json&key=xxxxxxxxxxxxxxxxxxxxxx&stpid=1324,1248,18261,11264

		$busStops = array('1324','1248','18261','11264','17674','11005','7859','7946');

		$cachedData = false;
		if(isset($_GET['file']) ){
			$cachedDataFile = __DIR__.'/'.$traceFilePath .$_GET['file'].'.json';

			if(FALSE !== $jsonCache = @file_get_contents( $cachedDataFile) ){
				if(NULL === $cachedData = json_decode($jsonCache, true)){
					$cachedData = false;
				}else{
				}
			}
		}

		if(FALSE === $cachedData){
			$nowTime = time();
		}else{
			//use cache file name for timestamp
			$fileTimeStr = substr($_GET['file'],0,10).' '.str_replace('-',':',substr($_GET['file'],11,8));
			$nowTime = strtotime($fileTimeStr);
		}

		if(FALSE === $cachedData){
			$busPredictionsResponse = $this->curlCall('bus','predictions', array('stpid'=>implode($busStops,',')));
			file_put_contents($traceFilePath.$traceFile.'.txt',print_r($busPredictionsResponse,true), FILE_APPEND);
			file_put_contents($traceFilePath.$traceFile.'.json','{"bus":'.json_encode($busPredictionsResponse).',', FILE_APPEND);
		}else{
			$busPredictionsResponse = $cachedData['bus'];
		}
		

		$busPredictionsByStop = array();
		foreach($busStops as $stop){
			$busPredictionsByStop[$stop] = array();
		}

		if(isset($busPredictionsResponse['bustime-response']['prd'])){
			foreach($busPredictionsResponse['bustime-response']['prd'] as $bus){
				$busPredictionsByStop[$bus['stpid']][] = array(
					'eta' => $bus['prdctdn'],
					'dest' => $bus['des']
				);
			}
		}
        //$busPredictionsByStop['18261'] = array('1','2','3');
		$busPredictionsHtml = array();
		foreach($busPredictionsByStop as $key=>$stop){
			$busPredictionsHtml[$key] = '';
			foreach($stop as $stopData){
				$busPredictionsHtml[$key] .= "<DIV class='bus'>\r\n";
				$busPredictionsHtml[$key] .= "	<DIV class='time'>{$stopData['eta']}\r\n";
				$busPredictionsHtml[$key] .= "		<SPAN class='dest'>{$stopData['dest']}</SPAN>\r\n";
				$busPredictionsHtml[$key] .= "	</DIV>\r\n";
				$busPredictionsHtml[$key] .= "</DIV>\r\n";
			}
		}


		//GET TRAIN TIMES:
		$trainStop = '41020';

		//http://lapi.transitchicago.com/api/1.0/ttpositions.aspx?rt=blue&key=xxxxxxxxxxxxxxxxxxxx&outputType=JSON
		if(FALSE === $cachedData){
			$trainArrivalsResponse = $this->curlCall('train','arrivals', array('mapid'=>$trainStop));
			file_put_contents($traceFilePath.$traceFile.'.txt',print_r($trainArrivalsResponse,true), FILE_APPEND);
			file_put_contents($traceFilePath.$traceFile.'.json','"train":'.json_encode($trainArrivalsResponse).'}', FILE_APPEND);
		}else{
			$trainArrivalsResponse = $cachedData['train'];
		}
		//trDr
		//1 = O’Hare-bound (North)
		//5 = Forest Park-bound

		$trainArrivalsByDirection = array(
			'North'=>array(),
			'South'=>array()
		);

		if(isset($trainArrivalsResponse['ctatt']['eta'])){
			foreach($trainArrivalsResponse['ctatt']['eta'] as $train){

				$time = strtotime($train['arrT']);
				$time = $time - $nowTime;
				if($time > 0){
					$time =  round($time / 60);
				}

				switch($train['trDr']){
					case '1':
							$trainArrivalsByDirection['North'][] = array(
								'eta' => $time,
								'dest' => $train['destNm']
							);
						break;
					case '5':
							$trainArrivalsByDirection['South'][] = array(
								'eta' => $time,
								'dest' => $train['destNm']
							);
						break;
				}
			}
		}

		$trainPredictionsHtml = array(
			'North'=>'',
			'South'=>''
		);
		foreach($trainArrivalsByDirection as $key=>$stop){
			foreach($stop as $stopData){
				$trainPredictionsHtml[$key] .= "<DIV class='train'>\r\n";
				$trainPredictionsHtml[$key] .= "	<DIV class='time'>{$stopData['eta']}\r\n";
				$trainPredictionsHtml[$key] .= "		<SPAN class='dest'>{$stopData['dest']}</SPAN>\r\n";
				$trainPredictionsHtml[$key] .= "	</DIV>\r\n";
				$trainPredictionsHtml[$key] .= "</DIV>\r\n";
			}
		}

		//echo "<PRE>";
		//print_r($trainArrivalsByDirection);
		//print_r($trainArrivalsResponse);
		//exit;







		echo <<<CSS
<link rel="stylesheet" type="text/css" href="styles.css">
<div id="refreshTimer" class="refreshTimer"></div>
CSS;

		echo "<BR><DIV class='main-container'>\r\n";
		echo "<DIV class='row'>";
		echo "	<DIV class='box'></DIV>\r\n";
		echo "	<DIV class='box dir-n'>\r\n";
		echo "		<DIV class='route'>\r\n";
		echo "			<DIV class='route-label'>\r\n";
		echo "				<DIV class='route-name'>Blue Line</DIV>\r\n";
		echo "				<DIV class='route-intersection'>Logan Square</DIV>";
		echo "			</DIV>\r\n";
		echo "			<DIV class='route-times'>{$trainPredictionsHtml['North']}</DIV>";
		echo "		</DIV>\r\n";
		echo "		<DIV class='route'>\r\n";
		echo "			<DIV class='route-label'>\r\n";
		echo "				<DIV class='route-name'>#82 Kimball</DIV>\r\n";
		echo "				<DIV class='route-intersection'>Kimball & Wrightwood</DIV>";
		echo "			</DIV>\r\n";
		echo "			<DIV class='route-times'>{$busPredictionsHtml['11264']}</DIV>";
		echo "		</DIV>\r\n";
		echo "	</DIV>\r\n";
		ECHO "	<DIV class='box'></DIV>\r\n";
		echo "</DIV>\r\n";
		echo "<DIV class='row'>";
		echo "	<DIV class='box dir-w'>\r\n";
		echo "		<DIV class='route'>\r\n";
		echo "			<DIV class='route-label'>\r\n";
		echo "				<DIV class='route-name'>#77 Belmont</DIV>\r\n";
		echo "				<DIV class='route-intersection'>Belmont & Spaulding</DIV>";//Kimball  for 7947
		echo "			</DIV>\r\n";
		echo "			<DIV class='route-times'>{$busPredictionsHtml['7946']}</DIV>";//7947 under construction
		echo "		</DIV>\r\n";
		echo "		<DIV class='route'>\r\n";
		echo "			<DIV class='route-label'>\r\n";
		echo "				<DIV class='route-name'>#76 Diversey</DIV>\r\n";
		echo "				<DIV class='route-intersection'>Logan Square Blue Line</DIV>";
		echo "			</DIV>\r\n";
		echo "			<DIV class='route-times'>{$busPredictionsHtml['17674']}</DIV>";
		echo "		</DIV>\r\n";
		echo "		<DIV class='route'>\r\n";
		echo "			<DIV class='route-label'>\r\n";
		echo "				<DIV class='route-name'>#74 Fullerton</DIV>\r\n";
		echo "				<DIV class='route-intersection'>Fullerton & Kedzie</DIV>";
		echo "			</DIV>\r\n";
		echo "			<DIV class='route-times'>{$busPredictionsHtml['1248']}</DIV>";
		echo "		</DIV>\r\n";
		echo "	</DIV>\r\n";
		echo "	<DIV class='box compass'></DIV>\r\n";
		echo "	<DIV class='box dir-e'>\r\n";
		echo "		<DIV class='route'>\r\n";
		echo "			<DIV class='route-label'>\r\n";
		echo "				<DIV class='route-name'>#77 Belmont</DIV>\r\n";
		echo "				<DIV class='route-intersection'>Belmont & Spaulding</DIV>"; // Kimball for 7858
		echo "			</DIV>\r\n";
		echo "			<DIV class='route-times'>{$busPredictionsHtml['7859']}</DIV>";//7858 under construction
		echo "		</DIV>\r\n";
		echo "		<DIV class='route'>\r\n";
		echo "			<DIV class='route-label'>\r\n";
		echo "				<DIV class='route-name'>#76 Diversey</DIV>\r\n";
		echo "				<DIV class='route-intersection'>Logan Square Blue Line</DIV>";
		echo "			</DIV>\r\n";
		echo "			<DIV class='route-times'>{$busPredictionsHtml['11005']}</DIV>";
		echo "		</DIV>\r\n";
		echo "		<DIV class='route'>\r\n";
		echo "			<DIV class='route-label'>\r\n";
		echo "				<DIV class='route-name'>#74 Fullerton</DIV>\r\n";
		echo "				<DIV class='route-intersection'>Fullerton & Kedzie</DIV>";
		echo "			</DIV>\r\n";
		echo "			<DIV class='route-times'>{$busPredictionsHtml['1324']}</DIV>";
		echo "		</DIV>\r\n";
		echo "	</DIV>\r\n";
		echo "</DIV>\r\n";
		echo "<DIV class='row'>";
		echo "	<DIV class='box'></DIV>\r\n";
		echo "	<DIV class='box dir-s'>\r\n";
		echo "		<DIV class='route'>\r\n";
		echo "			<DIV class='route-label'>\r\n";
		echo "				<DIV class='route-name'>Blue Line</DIV>\r\n";
		echo "				<DIV class='route-intersection'>Logan Square</DIV>";
		echo "			</DIV>\r\n";
		echo "			<DIV class='route-times'>{$trainPredictionsHtml['South']}</DIV>";
		echo "		</DIV>\r\n";
		echo "		<DIV class='route'>\r\n";
		echo "			<DIV class='route-label'>\r\n";
		echo "				<DIV class='route-name'>#82 Kimball</DIV>\r\n";
		echo "				<DIV class='route-intersection'>Kimball & Wrightwood</DIV>";
		echo "			</DIV>\r\n";
		echo "			<DIV class='route-times'>{$busPredictionsHtml['18261']}</DIV>";
		echo "		</DIV>\r\n";
		echo "	</DIV>\r\n";
		echo "	<DIV class='box'></DIV>\r\n";
		echo "</DIV>\r\n";
		echo "</DIV>\r\n";
		echo "</DIV>\r\n";
		if(FALSE === $cachedData){
			echo "<div>{$traceFile} - <a target='_blank' href='?file={$traceFile}'>Link</a> - <a target='_blank' href='{$traceFilePath}{$traceFile}.txt'>TXT</a> - <a target='_blank' href='{$traceFilePath}{$traceFile}.json'>JSON</a></div>";
		}else{
			echo "<div>CACHED! {$traceFile} - <a target='_blank' href='?file={$_GET['file']}'>Link</a> - <a target='_blank' href='{$traceFilePath}{$_GET['file']}.txt'>TXT</a> - <a target='_blank' href='{$traceFilePath}{$_GET['file']}.json'>JSON</a></div>";
		}

		echo <<<JAVSCRIPT
<script type="text/javascript">

(function(){
	var refreshTicker = 30;
	var timerEl = document.getElementById('refreshTimer');
	var refreshIntervalTick = function(){
		timerEl.innerHTML = refreshTicker;
		if(refreshTicker <= 0){
			location.reload(true);
			return;
		}
		refreshTicker--;
		setTimeout(refreshIntervalTick,1000);
	};
	refreshIntervalTick();
})();


</script>
JAVSCRIPT;

		//echo "<PRE>";
		//print_r($busPredictionsByStop);
		//print_r($busPredictionsResponse['bustime-response']['prd']);
	}

}

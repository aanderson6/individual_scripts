<?php

namespace Icinga\Module\mygrafana\Controllers;

use Icinga\Web\Controller;

class CoregraphController extends Controller
{

    private $link = 'mygrafana/coregraph/graph';
    private $parameters = array(
        'timerange' => null
    );
    private $grafanaurl = "";

    static $timeRanges = array(
        'Minutes' => array(
            '5m' => '5 minutes',
            '15m' => '15 minutes',
            '30m' => '30 minutes',
            '45m' => '45 minutes'
        ),
        'Hours' => array(
            '1h' => '1 hour',
            '3h' => '3 hours',
            '6h' => '6 hours',
            '8h' => '8 hours',
            '12h' => '12 hours',
            '24h' => '24 hours'
        ),
'Days' => array(
            '2d' => '2 days',
            '7d' => '7 days',
            '14d' => '14 days',
            '30d' => '30 days',
        ),
        'Months' => array(
            '2M' => '2 month',
            '6M' => '6 months',
            '9M' => '9 months'
        ),
        'Years' => array(
            '1y' => '1 year',
            '2y' => '2 years',
            '3y' => '3 years'
        ),
        'Special' => array(
            '1d/d' => 'Yesterday',
            '2d/d' => 'Day b4 yesterday',
            '1w/w' => 'Previous week',
            '1M/M' => 'Previous month',
            '1Y/Y' => 'Previous Year',
        )
    );

    private function getTimerangeLink($rangeName, $timeRange)
    {
        $this->parameters['timerange'] = $timeRange;

        return $this->view->qlink(
            $rangeName,
            $this->link,
            $this->parameters,
            array(
                'class' => 'action-link',
                'data-base-target' => '_self',
                'title' => 'Set timerange for graph(s) to ' . $rangeName
            )
        );
    }

protected function isValidTimeStamp($timestamp)
    {
        return ((string) (int) $timestamp === $timestamp)
            && ($timestamp <= PHP_INT_MAX)
            && ($timestamp >= ~PHP_INT_MAX);
    }

    private function getTimerangeMenu($timerange = "", $timerangeto = "")
    {

        $menu = '<table class="grafana-table"><tr>';

        foreach (self::$timeRanges as $key => $mainValue) {
            $menu .= '<td><ul class="grafana-menu-navigation"><a class="main" href="#">' . $key . '</a>';
            $counter = 1;
            foreach ($mainValue as $subkey => $value) {
                $menu .= '<li class="grafana-menu-n' . $counter . '">' . $this->getTimerangeLink($value,
                        $subkey) . '</li>';
                $counter++;
            }
            $menu .= '</ul></td>';
        }

        $timerange = urldecode($timerange);
        $timerangeto = urldecode($timerangeto);

        if($this->isValidTimeStamp($timerange)) {
            $d = new \DateTime();
            $d->setTimestamp($timerange/1000);
            $timerange = $d->format("Y-m-d H:i:s");
        }

        if($this->isValidTimeStamp($timerangeto)) {
            $d = new \DateTime();
            $d->setTimestamp($timerangeto/1000);
            $timerangeto = $d->format("Y-m-d H:i:s");
        }

        $menu .= '</tr></table>';
        return $menu;
    }

private function getMyimageHtml($dashboarduid, $dashboard, $panelId, $orgId, $timerange, $timerangeto)
    {
        // Test whether curl is loaded
        if (extension_loaded('curl') === false) {
            $imageHtml = $this->translate('CURL extension is missing. Please install CURL for PHP and ensure it is loaded.');
            return false;
        }
        $pngUrl = sprintf(
            'http://icinga.domain.org:3000/render/d-solo/%s/%s?viewPanel=%s&orgId=%s&width=640&height=280&theme=dark&from=%s&to=%s',
            $dashboarduid,
            $dashboard,
            $panelId,
            $orgId,
            urlencode($timerange),
            urlencode($timerangeto)
        );

        $this->grafanaurl = $pngUrl;

        // fetch image with curl
        $curl_handle = curl_init();
        $curl_opts = array(
            CURLOPT_URL => $pngUrl,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => "0",
            CURLOPT_TIMEOUT => "5",
            CURLOPT_HTTPHEADER => array('Content-Type: application/json' , "Authorization: Bearer keygoeshere")
        );

        curl_setopt_array($curl_handle, $curl_opts);
        $imageHtml = curl_exec($curl_handle);

        $statusCode = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);

        if ($imageHtml === false) {
            $imageHtml = 'Cannot fetch graph with curl: '. curl_error($curl_handle);
        }

        if ($statusCode > 299) {
            $error = @json_decode($res);
            $imageHtml = 'Cannot fetch Grafana graph: ' . ($error !== null && property_exists($error, 'message') ? $error->message : "");
        }

        curl_close($curl_handle);
        return $imageHtml;
    }

    private function getMyimageHtml2($dashboarduid, $dashboard, $panelId, $orgId, $timerange, $timerangeto) {

        $test = sprintf('<iframe src="https://icinga.domain.org/grafana/d-solo/%s/%s?orgId=%s&from=now-%s&to=%s&panelId=%s" width="450" height="200" frameborder="0"></iframe>',
            $dashboarduid,
            $dashboard,
            $orgId,
            urlencode($timerange),
            urlencode($timerangeto),
            $panelId);

        return $test;
    }
    


    


    public function graphAction()
    {
/* save timerange from params for later use */
$timerange = $this->hasParam('timerange') ? urldecode($this->getParam('timerange')) : null;
if($this->hasParam('timerangeto')) {
    $timerangeto = urldecode($this->getParam('timerangeto'));
} else {
    $timerangeto = "now";
}

if($timerange == null) {
    $timerange = '6h';
}

$this->parameters = array(
        'timerange' => $timerange
    );


$this->view->menu = $this->getTimerangeMenu();

$this->view->test = '<div class="mygrid">';
for ($x = 2; $x <= 71; $x++) {
    $this->view->test .= $this->getMyimageHtml2("6TLVauS4k", "site-core-switches", $x, "1", $timerange, $timerangeto);
  }



$this->view->test .= '</div>';

$this->view->grafanaurl = $this->grafanaurl;
#print '<div>' . $menu . '</div>';
#print '<div class="icinga-module module-grafana" style="display: inline-block;">' . 'title' . $test . '</div>';
}

}
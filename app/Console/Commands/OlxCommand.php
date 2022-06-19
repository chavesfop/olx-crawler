<?php

namespace App\Console\Commands;

use App\Models\OlxAds;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Wa72\HtmlPageDom\HtmlPageCrawler;

class OlxCommand extends Command
{
    protected $signature = 'olx:crawler';
    protected $description = 'Run olx crawler';

    private $_lastPage = 0;
    private Client $_client;

    public function __construct()
    {
        OlxAds::query()->truncate();
        $this->_client = new Client();
        parent::__construct();
    }

    private function getLastPageUrl($dom)
    {
        $dom->filter('.iRQkdN');
        $pages = $dom->filter('.iRQkdN');

        $lastPageUrl = (($pages->getNode($pages->count() - 1)->getAttribute('href')));
        $lastPageArr = explode('?', $lastPageUrl);
        $lastPageUrl = $lastPageArr[1];
        $lastPageArr = explode('&', $lastPageUrl);
        $lastPageUrl = $lastPageArr[0];
        $lastPageArr = explode('=', $lastPageUrl);
        return $lastPageArr[1];
    }

    private function getAds($uf, $page, $query)
    {
        $q = urlencode($query);
        $url = "https://{$uf}.olx.com.br/eletronicos-e-celulares?q={$q}";
        if ($page > 1){
            $url .= "&o={$page}";
        }

        //$this->info("Connecting to {$url} and starting operations");
        $response = $this->_client->request('GET', $url);
        $dom = HtmlPageCrawler::create($response->getBody()->getContents());
        $this->_lastPage = $this->getLastPageUrl($dom);
        $list = $dom->filter('ul#ad-list');
        return $list->children('li');
    }

    private function extraData(OlxAds $olxAds)
    {
        $response = $this->_client->request('GET', $olxAds->url);
        $dom = HtmlPageCrawler::create($response->getBody()->getContents());
        
    }

    public function handle(){
        $query = 'lenovo';
        $uf = ['sc', 'pr'];
        foreach ($uf as $u){
            $ads = $this->getAds($u, 1, $query);
            $this->output->info("Starting searching for {$query} in {$u}");
            $this->output->progressStart($this->_lastPage);
            for ($o = 1; $o <= $this->_lastPage; $o++){
                $this->output->progressAdvance();
                if ($o > 1){
                    $ads = $this->getAds($u, $o, $query);
                }
                $this->output->progressStart($ads->count());
                foreach ($ads as $ad){
                    try {
                        $olxAds = new OlxAds;
                        $olxAds->query = $query;

                        $li = HtmlPageCrawler::create($ad);
                        $span = $li->filter('span.eoKYee');
                        $a = $li->filter('a');
                        $olxAds->url = $a->getAttribute('href');
                        $olxAds->title = $a->getAttribute('title');

                        //filter search only by title
                        if (stripos($olxAds->title, $query) === false)
                        {
                            continue;
                        }

                        $price = str_replace(['R$', '.'], '', $span->getInnerHtml());
                        $price = str_replace(',', '.', $price);
                        $price = (float)trim($price);
                        $olxAds->price = $price;

                        $img = $li->filter('img');
                        $olxAds->image = $img->getAttribute('src');

                        $date = $li->filter('span.wlwg1t-1');
                        $dt_time = '';
                        $tmp = new \DateTime();
                        foreach ($date as $dt){
                            switch($dt->nodeValue){
                                case 'Hoje':
                                    $dt_time = $tmp->format('Y-m-d');
                                    break;
                                case 'Ontem':
                                    $dt_time = $tmp->sub(new \DateInterval('P1D'));
                                    $dt_time = $tmp->format('Y-m-d');
                                    break;
                                default:
                                    $dt_time = " {$dt->nodeValue}:00";
                                    break;
                            }
                        }
                        $olxAds->creation_date = $dt_time;

                        $location = $li->filter('span.ciykCV');
                        $location = ($location->getInnerHtml());
                        $location = str_replace(',', '-', $location);
                        $olxAds->location = $location;
                        $this->extraData($olxAds);
                        $olxAds->save();


                    } catch (\Exception $e){
                        continue;
                    }
                }
            }
            $this->output->progressFinish();
        }
    }
}

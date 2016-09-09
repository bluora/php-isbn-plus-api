<?php

namespace Bluora\IsbnPlus;

class IsbnPlus
{
    /**
     * ISBN Plus ID
     *
     * @var string
     */
    private $client_id;

    /**
     * ISBN Plus Key
     *
     * @var string
     */
    private $client_key;

    /**
     * ISBN Plus URL
     *
     * @var string
     */
    private $client_url = 'https://api-2445581351187.apicast.io/search';

    /**
     * Search key
     * @var integer
     */
    private $search_key = 'q';

    /**
     * Search text
     * @var integer
     */
    private $search_text = '';

    /**
     * Current page.
     *
     * @var integer
     */
    private $current_page = 1;

    /**
     * Current order.
     *
     * @var integer
     */
    private $current_order = 'published';

    /**
     * Current result.
     *
     * @var integer
     */
    private $current_result = [];

    /**
     * Total pages in result.
     *
     * @var integer
     */
    private $result_total_pages = 0;

    /**
     * Total books in result.
     *
     * @var integer
     */
    private $result_total_count = 0;

    /**
     * Results by page.
     *
     * @var array
     */
    private $page_results = [];

    /**
     * Last response from a request.
     *
     * @var mixed
     */
    private $last_response = null;

    /**
     * Last error code.
     *
     * @var mixed
     */
    private $last_code = null;

    /**
     * Last error from a request.
     *
     * @var mixed
     */
    private $last_error = null;

    /**
     * Create an instance of the client.
     *
     * @return IsbnPlus
     */
    public function __construct()
    {
        $this->setEnv('id');
        $this->setEnv('key');
        $this->setEnv('url');
        return $this;
    }

    /**
     * Set an environment variable.
     *
     * @param string $env_name
     *
     * @return IsbnPlus
     */
    public function setEnv($env_name)
    {
        $method = 'setRemote'.ucfirst(strtolower($env_name));
        $property = 'client_'.strtolower($env_name);
        $set_value = (getenv('ISBN_PLUS_'.strtoupper($env_name))) ? getenv('ISBN_PLUS_'.strtoupper($env_name)) : '';
        if (!empty($set_value)) {
            if (method_exists($this, $method)) {
                return $this->$method($set_value);
            } elseif (property_exists($this, $property)) {
                $this->$property = $set_value;
            }
        }
        return $this;
    }

    /**
     * Check if this client has been provided a property value.
     *
     * @param $name
     *
     * @return bool
     */
    public function hasConfig($name)
    {
        if (property_exists($this, 'client_'.$name)) {
            $name = 'client_'.$name;
            return !empty($this->$name);
        }
        return false;
    }

    /**
     * Set a config variable.
     *
     * @param string $name
     * @param string $value
     *
     * @return PwsCloud
     */
    public function setConfig($name, $value)
    {
        if (property_exists($this, 'client_'.$name) && !empty($value)) {
            $name = 'client_'.$name;
            $this->$name = $value;
        }

        return $this;
    }

    public function page($page)
    {
        $this->current_page = $page;
        return $this;
    }

    public function next()
    {
        if ($this->current_page < $result_total_pages) {
            $this->current_page++;
            return $this->get();
        }
        return false;
    }

    public function previous()
    {
        if ($this->current_page > 1) {
            $this->current_page--;
            return $this->get();
        }
        return false;
    }

    public function everything($text)
    {
        $this->search_key = 'q';
        $this->search_text = $text;
        return $this;
    }

    public function author($text)
    {
        $this->search_key = 'a';
        $this->search_text = $text;
        return $this;
    }

    public function category($text)
    {
        $this->search_key = 'c';
        $this->search_text = $text;
        return $this;
    }

    public function bookSeries($text)
    {
        $this->search_key = 's';
        $this->search_text = $text;
        return $this;
    }

    public function bookTitle($text)
    {
        $this->search_key = 't';
        $this->search_text = $text;
        return $this;
    }

    /**
     * Get the data.
     *
     * @return boolean|integer
     */
    public function get()
    {
        if (!$this->hasConfig('id') || !$this->hasConfig('key') || !$this->hasConfig('url')) {
           throw new Exception\MissingConfigException('Missing required API config.');
        }
        if (isset($this->page_results[$this->current_page])) {
            return $this->current_result = $this->page_results[$this->current_page];
        }

        $this->last_error = null;
        $this->last_code = null;
        $this->last_http_code = null;

        $query_data = [];
        $query_data[$this->search_key] = $this->search_text;
        $query_data['p'] = $this->current_page;
        $query_data['order'] = $this->current_order;
        $query_data['app_id'] = $this->client_id;
        $query_data['app_key'] = $this->client_key;

        $headers = ['Accept: application/json'];

        $url = $this->client_url;
        $url .= '?'.http_build_query($query_data);
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_PORT, 443);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $response_curl = curl_exec($curl);
        $this->last_code = curl_errno($curl);
        $this->last_http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($this->last_code === 0 && $this->last_http_code < 300) {
            $response_xml = simplexml_load_string($response_curl, "SimpleXMLElement", LIBXML_NOCDATA);
            $response_json = json_encode($response_xml);
            $this->last_response = json_decode($response_json, true);

            $this->result_total_count = $this->last_response['page']['count'];
            $this->result_total_pages = $this->last_response['page']['pages'];

            if (!isset($this->last_response['page']['results']['book'][0])) {
                $this->current_result = [$this->last_response['page']['results']['book']];
            } else {
                $this->current_result = $this->last_response['page']['results']['book'];
            }
        } elseif ($this->last_code === 0) {
            $this->last_error = $response_curl;
        } else {
            $this->last_code = -$this->last_code;
            $this->last_error = curl_error($curl);
        }
        curl_close($curl);
        return $this;
    }

    /**
     * Most recent query failed.
     *
     * @return boolean
     */
    public function error()
    {
        return ($this->last_code < 0 || $this->last_http_code >= 400);
    }

    /**
     * Get the current result.
     *
     * @return object
     */
    public function first()
    {
        if (isset($this->current_result[0])) {
            return $this->current_result[0];
        }
        return null;
    }

    /**
     * Get the current result.
     *
     * @return object
     */
    public function getResult()
    {
        return $this->current_result;
    }

    /**
     * Get the error message.
     *
     * @return object
     */
    public function getCode()
    {
        return $this->last_code;
    }

    /**
     * Get the error message.
     *
     * @return object
     */
    public function getHttpCode()
    {
        return $this->last_http_code;
    }

    /**
     * Get the error message.
     *
     * @return object
     */
    public function getError()
    {
        return $this->last_error;
    }
}

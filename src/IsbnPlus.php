<?php

namespace Bluora\IsbnPlus;

class IsbnPlus implements \Iterator
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
     * Total curl requests made.
     *
     * @var integer
     */
    private $curl_request_count = 0;

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
     * Current record.
     *
     * @var integer
     */
    private $current_record = 1;

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
     * Limit the records.
     *
     * @var integer
     */
    private $query_limit = false;

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
     * Last error code.
     *
     * @var mixed
     */
    private $last_http_code = null;

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
     * @return IsbnPlus
     */
    public function setConfig($name, $value)
    {
        if (property_exists($this, 'client_'.$name) && !empty($value)) {
            $name = 'client_'.$name;
            $this->$name = $value;
        }

        return $this;
    }

    /**
     * Set the page.
     *
     * @param  integer $page
     *
     * @return IsbnPlus
     */
    public function page($page)
    {
        $this->current_page = $page;
        $this->get();
        return $this;
    }

    /**
     * Set the record limit
     *
     * @param  integer $limit
     *
     * @return IsbnPlus
     */
    public function limit($limit)
    {
        $this->query_limit = $limit;
        return $this;
    }

    /**
     * Search on any field.
     *
     * @param  string $text
     *
     * @return IsbnPlus
     */
    public function everything($text)
    {
        $this->search_key = 'q';
        $this->search_text = $text;
        return $this;
    }

    /**
     * Search on the author field.
     *
     * @param  string $text
     *
     * @return IsbnPlus
     */
    public function author($text)
    {
        $this->search_key = 'a';
        $this->search_text = $text;
        return $this;
    }

    /**
     * Search on the category field.
     *
     * @param  string $text
     *
     * @return IsbnPlus
     */
    public function category($text)
    {
        $this->search_key = 'c';
        $this->search_text = $text;
        return $this;
    }

    /**
     * Search on the book series title field.
     *
     * @param  string $text
     *
     * @return IsbnPlus
     */
    public function bookSeries($text)
    {
        $this->search_key = 's';
        $this->search_text = $text;
        return $this;
    }

    /**
     * Search on the book title field.
     *
     * @param  string $text
     *
     * @return IsbnPlus
     */
    public function bookTitle($text)
    {
        $this->search_key = 't';
        $this->search_text = $text;
        return $this;
    }

    /**
     * Get the data.
     *
     * @return IsbnPlus
     */
    public function get()
    {
        if (!$this->hasConfig('id') || !$this->hasConfig('key') || !$this->hasConfig('url')) {
           throw new Exception\MissingConfigException('Missing required API config.');
        }
        if (isset($this->page_results[$this->current_page])) {
            $this->current_result = $this->page_results[$this->current_page];
            return $this;
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
        $this->curl_request_count++;

        if ($this->last_code === 0 && $this->last_http_code < 300) {
            $response_xml = simplexml_load_string($response_curl, "SimpleXMLElement", LIBXML_NOCDATA);
            $response_json = json_encode($response_xml);
            $this->last_response = json_decode($response_json, true);

            $this->result_total_count = $this->last_response['page']['count'];
            $this->result_total_pages = $this->last_response['page']['pages'];

            if (empty($this->last_response['page']['results'])) {
                $this->current_result = [];
            } elseif (!isset($this->last_response['page']['results']['book'][0])) {
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
     * @return array
     */
    public function getResult()
    {
        return $this->current_result;
    }

    /**
     * Get the error message.
     *
     * @return integer
     */
    public function getCode()
    {
        return $this->last_code;
    }

    /**
     * Get the error message.
     *
     * @return integer
     */
    public function getHttpCode()
    {
        return $this->last_http_code;
    }

    /**
     * Get the error message.
     *
     * @return string
     */
    public function getError()
    {
        return $this->last_error;
    }

    /**
     * Previous record.
     *
     * @return array
     */
    public function previous()
    {
        $this->current_record--;
        if ($this->current_record < 0) {
            $this->current_page--;
            $this->current_record = 9;
            $this->get();
        }
        return $this->current();
    }

    /**
     * Rewind the result.
     *
     * @return array
     */
    public function rewind()
    {
        $this->current_page = 1;
        $this->current_record = 0;
        $this->get();
        return $this->current();
    }

    /**
     * Get the current result.
     *
     * @return array|null
     */
    public function current()
    {
        if ($this->curl_request_count == 0) {
            $this->get();
        }
        if (isset($this->current_result[$this->current_record])) {
            return $this->current_result[$this->current_record];
        }
        return null;
    }

    /**
     * Get the row key.
     *
     * @return integer
     */
    public function key() 
    {
        return (($this->current_page - 1) * 10) + $this->current_record;
    }

    /**
     * Rewind the result.
     *
     * @return array
     */
    public function next()
    {
        $this->current_record++;
        if ($this->current_record >= 10) {
            $this->current_page++;
            $this->current_record = 0;
            $this->get();
        }
        return $this->current();
    }

    /**
     * Has results.
     *
     * @return boolean
     */
    public function valid()
    {
        if ($this->curl_request_count == 0) {
            return true;
        }
        if ($this->query_limit !== false && $this->query_limit < $this->key()+1) {
            return false;
        }
        return !$this->error() && ((($this->current_page - 1) * 10) + $this->current_record) <= $this->result_total_count;
    }

}

<?php

namespace Stanford\CloudStorage;

class CloudUpload {
    protected $url;
    protected $headers;

    public function __construct($url, $headers) {
        $this->url = $url;
        $this->headers = $headers;
    }

    public function setUrl($url) {
        $this->url = $url;
        return $this;
    }

    public function setHeaders($headers) {
        $this->headers = $headers;
        return $this;
    }

    public function getUrl() {
        return $this->url;
    }

    public function getHeaders() {
        return $this->headers;
    }
}
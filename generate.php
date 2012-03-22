<?php

/* Copyright (c) 2012 Nathan Osman
   
   Permission is hereby granted, free of charge, to any person obtaining a copy
   of this software and associated documentation files (the "Software"), to deal
   in the Software without restriction, including without limitation the rights
   to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
   copies of the Software, and to permit persons to whom the Software is
   furnished to do so, subject to the following conditions:
   
   The above copyright notice and this permission notice shall be included in
   all copies or substantial portions of the Software.
   
   THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
   IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
   FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
   AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
   LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
   OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
   THE SOFTWARE. */

class Generator
{
    // Constants
    const Site       = 'http://api.stackexchange.com';
    const UserAgent  = 'Stack Cartographer 0.1';
    
    // Member variables
    private $curl_handle = null;
    
    // Initializes the generator
    function __construct()
    {
        $this->curl_handle = curl_init();
    }
    
    // Returns a DOMDocument instance from the specified URL
    // or (if provided) executes an XPath query and returns the results
    private function FetchDOM($url, $query=null)
    {
        curl_setopt($this->curl_handle, CURLOPT_URL, $url);
        curl_setopt($this->curl_handle, CURLOPT_USERAGENT, self::UserAgent);
        curl_setopt($this->curl_handle, CURLOPT_RETURNTRANSFER, 1);
        
        $data = curl_exec($this->curl_handle);
        
        // Perform some simple error checking
        if($data === FALSE)
            throw new Exception('cURL reported "' . curl_error($this->curl_handle) . '"');
        
        // Construct a DOMDocument from the data we received and return it
        $document = new DOMDocument();
        @$document->loadHTML($data);
        
        if($document === FALSE)
            throw new Exception('There was an error parsing the HTML retrieved.');
        
        if($query !== null)
        {
            $path = new DOMXPath($document);
            return $path->query($query);
        }
        else
            return $document;
    }
    
    // Returns a list of all pages containing API methods
    private function GetMethodList()
    {
        // Fetch all of the '.method-name a' elements
        $links = $this->FetchDOM(self::Site . '/docs', '//div[@class="method-name"]/a/@href');
        
        // Convert the results into an array and return it
        $urls = array();
        foreach($links as $link)
            $urls[] = $link->nodeValue;
        
        return array_unique($urls);
    }
    
    // Begins and manages the entire generation process
    public function Go()
    {
        echo "Fetching list of all methods...\n";
        $methods = $this->GetMethodList();
        echo count($methods) . " methods found - parsing them one at a time...\n";
    }
}

$g = new Generator();
$g->Go();

?>
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
    private $last_request = 0;  // timestamp of the last request
    
    // Initializes the generator
    function __construct()
    {
        // Turn off errors and initialize cURL
        libxml_use_internal_errors(TRUE);
        $this->curl_handle = curl_init();
    }
    
    // Returns a DOMDocument instance from the specified URL
    // or (if provided) executes an XPath query and returns the results
    private function FetchDOM($url, $query=null)
    {
        // Slam on the brakes if we're making requests too fast
        $diff = microtime(TRUE) - $this->last_request;
        if($diff < 0.5)
            usleep((0.5 - $diff) * 1000000);
        
        curl_setopt($this->curl_handle, CURLOPT_URL, $url);
        curl_setopt($this->curl_handle, CURLOPT_USERAGENT, self::UserAgent);
        curl_setopt($this->curl_handle, CURLOPT_RETURNTRANSFER, 1);
        
        $data = curl_exec($this->curl_handle);
        
        // Perform some simple error checking
        if($data === FALSE)
            throw new Exception('cURL reported "' . curl_error($this->curl_handle) . '"');
        
        // Construct a DOMDocument from the data we received and return it
        $document = new DOMDocument();
        $document->loadHTML($data);
        
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
    
    // Returns the expected item in the document if found and throws an exception if not found
    private function FindInDocument($document, $query, $item)
    {
        $first_element = (new DOMXPath($document))->query($query);
        
        // Ensure at least one item was found
        if($first_element === FALSE || !$first_element->length)
            throw new Exception("Unable to locate $item in the DOM.");
        
        // Return the first item
        return $first_element->item(0);
    }
    
    // Returns a list of all pages containing API methods
    private function GetMethodList()
    {
        // Fetch all of the '.method-name a' elements
        $links = $this->FetchDOM(self::Site . '/docs', '//div[@class="method-name"]/a');
        if(!$links->length)
            throw new Exception('Unable to locate the method list in the DOM.');
        
        // Convert the results into an array and return it
        $urls = array();
        foreach($links as $link)
            $urls[$link->nodeValue] = $link->attributes->getNamedItem('href')->nodeValue;
        
        return $urls;
    }
    
    // Returns specific details about the method
    private function GetMethodDetails($name, $doc_url)
    {
        // Fetch the page with the details
        $document = $this->FetchDom(self::Site . $doc_url);
        
        // Grab the description
        $desc = $this->FindInDocument($document, '//div[@class="indented"]/p', 'the method description')->nodeValue;
        
        // Grab the <script> tag containing the parameters
        $script = $this->FindInDocument($document, '//script[contains(.,"var parameters")]', 'the parameter script')->nodeValue;
        if(preg_match('/var parameters = (.*?);/', $script, $matches))
        {
            $parameters = json_decode($matches[1], TRUE);
            
            // Contruct the JSON data to return
            return array('path'        => $name,
                         'description' => $desc,
                         'parameters'  => $parameters);
        }
        else
            throw new Exception('Unable to apply regular expression to parameter script.');
    }
    
    // Generates the JSON that will be written to the output file
    private function GenerateJSON($methods)
    {
        return json_encode(array('meta'    => array('generator' => self::UserAgent,
                                                    'date'      => date(DATE_RFC850)),
                                 'methods' => $methods),
                           JSON_PRETTY_PRINT);
    }
    
    // Begins and manages the entire generation process
    public function Go()
    {
        // Display copyright information
        echo "Stack Cartographer\nCopyright 2012 - Nathan Osman\n\n";
        
        try
        {
            echo "Fetching list of all methods...\n";
            $unparsed_methods = $this->GetMethodList();
            echo count($unparsed_methods) . " methods found - parsing them one at a time...\n";
            
            // Parse each of the methods we found
            $methods = array();
            foreach($unparsed_methods as $name => $doc_url)
            {
                echo " - Parsing $name...\n";
                $methods[] = $this->GetMethodDetails($name, $doc_url);
            }
            
            // Open the output file...
            echo "Writing results to output file...\n";
            $f = @fopen('map.json', 'w');
            if($f === FALSE)
                throw new Exception('Unable to write to output file.');
            
            // ...and write the results
            fwrite($f, $this->GenerateJSON($methods));
            fclose($f);
            
            echo "Mapping complete!";
        }
        catch(Exception $e)
        {
            echo 'Error: ' . $e->getMessage();
        }
    }
}

$g = new Generator();
$g->Go();

?>
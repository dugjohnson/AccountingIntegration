<?php

Class RoboFile extends \Robo\Tasks
{

    public function tozip()
    {
        $manifestData = json_decode(file_get_contents(__DIR__ . '/manifest.json'));
        $zipFileName = "AccountingIntegration-" . $manifestData->version . ".zip";

        $fileName = $this->askDefault(" What is the output filename? ", $zipFileName);

        $this->taskExec('zip -r ' . $fileName . ' README.md LICENSE files scripts manifest.json')->run();

        $this->say($zipFileName . " file generated successfully");
    }

}
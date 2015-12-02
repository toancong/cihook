<?php
/**
 * Notifier for Status Build
 *
 * @package default
 * @author Pham Cong Toan <toancong1920@gmail.com>
 **/

class NotFoundBuildDataException extends Exception
{
    public function __construct()
    {
        parent::__construct('Not found build data', 101);
    }
}


abstract class Builder
{
    public function __construct($data = [])
    {
        $this->data = $data;

        if (!$this->data) {
            throw new NotFoundBuildDataException;
        }
    }

    public abstract function getCommitId();
}


class Codeship extends Builder
{
    public function __construct($data = [])
    {
        parent::__construct(isset($data['build']) ? $data['build'] : null);
    }

    public function getCommitId(){

        return $this->data['commit_id'];
    }
}


abstract class Service
{
    public function __construct(Builder $builder, $config = [])
    {
        $this->builder = $builder;
        $this->config = $config;
    }

    public abstract function notify();
}


class BitbucketCodeshipSevice extends Service
{
    static $state = [
        'testing' => 'INPROGRESS',
        'success' => 'SUCCESSFUL',
        'fail' => 'FAILED',
        'error' => 'FAILED'
    ];

    private function getBuildData()
    {
        return [
            "state" => self::$state[$this->builder->data['status']],
            "key" => $this->builder->data['build_id'],
            "name" => $this->builder->data['branch'],
            "url" => $this->builder->data['build_url'],
            "description" => "Changes by ".$this->builder->data['committer']
        ];
    }

    public function notify()
    {
        $commitId = $this->builder->getCommitId();
        $repo = $this->builder->data['project_full_name'];
        $data = $this->getBuildData();

        $url = "https://api.bitbucket.org/2.0/repositories/$repo/commit/$commitId/statuses/build";
        $ch = curl_init($url);

        # Authenticate
        if (isset($this->config['auth'])) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->config['auth']['username'] . ":" . $this->config['auth']['password']);
        }
        # Setup request to send json via POST.
        $payload = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        # Return response instead of printing.
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        # Send request.
        $result = curl_exec($ch);
        curl_close($ch);
        # Print response.

        return $result;
    }
}

$builder = new Codeship(json_decode(file_get_contents('php://input'), true));

$config['auth'] = [
    'username' => getenv('BITBUCKET_USERNAME'),
    'password' => getenv('BITBUCKET_PASSWORD')
];

$service = new BitbucketCodeshipSevice($builder, $config);

$service->notify();

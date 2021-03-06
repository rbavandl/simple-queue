<?php

namespace SimpleQueue\Adapter;

use Aws\Sqs\SqsClient;
use SimpleQueue\Exception\NotSupportedException;
use SimpleQueue\Job;
use SimpleQueue\QueueAdapterInterface;
use DateTime;

/**
 * Class AwsSqsQueueAdapter
 *
 * @package SimpleQueue\Adapter
 * @author George Webb <george@webb.uno>
 */
class AwsSqsQueueAdapter implements QueueAdapterInterface
{
    /**
     * @var string
     */
    private $queueName;

    /**
     * @var SqsClient
     */
    private $sqsClient;

    /**
     * @var string
     */
    private $sqsUrl;

    /**
     * @var array
     */
    private $config;

    /**
     * AwsSqsQueueAdapter constructor.
     *
     * @param string    $queueName  The name of the SQS queue
     * @param SqsClient $sqsClient  An SQS client
     * @param array     $config     Array of config values
     */
    public function __construct($queueName, SqsClient $sqsClient, $config = array())
    {
        $this->queueName = $queueName;
        $this->sqsClient = $sqsClient;
        $this->sqsUrl = $this->sqsClient->getQueueUrl(array('QueueName' => $this->queueName))->get('QueueUrl');
        $this->config = $config;
    }

    /**
     * Send a job
     *
     * @access public
     * @param  Job $job
     * @return $this
     */
    public function push(Job $job)
    {
        $this->sqsClient->sendMessage(array(
            'QueueUrl' => $this->sqsUrl,
            'MessageBody' => $job->serialize()
        ));
        return $this;
    }

    /**
     * batch publish messages 
     *
     * @access public
     * @param array $messages
     * @throws NotSupportedException
     */
    public function batchPush(array $messages)
    {
        throw new NotSupportedException('Batch Push is not supported by AwsSqsQueueAdapter.');
    }

    /**
     * Schedule a job in the future
     *
     * @access public
     * @param  Job      $job
     * @param  DateTime $dateTime
     * @return $this
     */
    public function schedule(Job $job, DateTime $dateTime)
    {
        $now = new DateTime();
        $when = clone($dateTime);
        $delay = $when->getTimestamp() - $now->getTimestamp();

        $this->sqsClient->sendMessage(array(
            'QueueUrl' => $this->sqsUrl,
            'MessageBody' => $job->serialize(),
            'VisibilityTimeout' => $delay
        ));

        return $this;
    }

    /**
     * Wait and get job from a queue
     *
     * @access public
     * @return Job|null
     */
    public function pull()
    {
        $result = $this->sqsClient->receiveMessage(array(
            'QueueUrl' => $this->sqsUrl,
            'WaitTimeSeconds' => empty($this->config['LongPollingTime']) ? 0 : (int) $this->config['LongPollingTime']
        ));

        if ($result['Messages'] == null) {
            return null;
        }

        $resultMessage = array_pop($result['Messages']);

        $job = new Job();
        $job->setId($resultMessage['ReceiptHandle']);
        $job->unserialize($resultMessage['Body']);

        return $job;
    }


    /**
     * Wait and get multiple jobs from a queue
     *
     * @access public
     * @param array $args
     * @return array
     * @throws NotSupportedException
     */
    public function batchPull(array $args = [])
    {
        throw new NotSupportedException('Batch Pull is not supported by AwsSqsQueueAdapter.');
    }

    /**
     * Acknowledge a job
     *
     * @access public
     * @param  Job $job
     * @return $this
     */
    public function completed(Job $job)
    {
        $this->sqsClient->deleteMessage(array(
            'QueueUrl' => $this->sqsUrl,
            'ReceiptHandle' => $job->getId()
        ));
        return $this;
    }

    /**
     * Mark a job as failed
     *
     * @access public
     * @param  Job $job
     * @return $this
     */
    public function failed(Job $job)
    {
        $this->sqsClient->changeMessageVisibility(array(
            'QueueUrl' => $this->sqsUrl,
            'ReceiptHandle' => $job->getId(),
            'VisibilityTimeout' => 0
        ));
        return $this;
    }
}

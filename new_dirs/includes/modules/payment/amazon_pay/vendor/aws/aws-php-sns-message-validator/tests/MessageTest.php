<?php
namespace Aws\Sns;

use GuzzleHttp\Psr7\Request;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * @covers \Aws\Sns\Message
 */
class MessageTest extends TestCase
{
    public $messageData = array(
        'Message' => 'a',
        'MessageId' => 'b',
        'Timestamp' => 'c',
        'TopicArn' => 'd',
        'Type' => 'e',
        'Subject' => 'f',
        'Signature' => 'g',
        'SignatureVersion' => '1',
        'SigningCertURL' => 'h',
        'SubscribeURL' => 'i',
        'Token' => 'j',
    );

    public function testGetters()
    {
        $message = new Message($this->messageData);
        $this->assertIsArray($message->toArray());

        foreach ($this->messageData as $key => $expectedValue) {
            $this->assertTrue(isset($message[$key]));
            $this->assertEquals($expectedValue, $message[$key]);
        }
    }

    public function testIterable()
    {
        $message = new Message($this->messageData);

        $this->assertInstanceOf('Traversable', $message);
        foreach ($message as $key => $value) {
            $this->assertTrue(isset($this->messageData[$key]));
            $this->assertEquals($value, $this->messageData[$key]);
        }
    }

    /**
     * @dataProvider messageTypeProvider
     *
     * @param string $messageType
     */
    public function testConstructorSucceedsWithGoodData($messageType)
    {
        $this->assertInstanceOf('Aws\Sns\Message', new Message(
            ['Type' => $messageType] + $this->messageData
        ));
    }

    public function messageTypeProvider()
    {
        return [
            ['Notification'],
            ['SubscriptionConfirmation'],
            ['UnsubscribeConfirmation'],
        ];
    }

    public function testConstructorFailsWithNoType()
    {
        $this->expectException(\InvalidArgumentException::class);
        $data = $this->messageData;
        unset($data['Type']);
        new Message($data);
    }

    public function testConstructorFailsWithMissingData()
    {
        $this->expectException(\InvalidArgumentException::class);
        new Message(['Type' => 'Notification']);
    }

    public function testRequiresTokenAndSubscribeUrlForSubscribeMessage()
    {
        $this->expectException(\InvalidArgumentException::class);
        new Message(
            ['Type' => 'SubscriptionConfirmation'] + array_diff_key(
                $this->messageData,
                array_flip(['Token', 'SubscribeURL'])
            )
        );
    }

    public function testRequiresTokenAndSubscribeUrlForUnsubscribeMessage()
    {
        $this->expectException(\InvalidArgumentException::class);
        new Message(
            ['Type' => 'UnsubscribeConfirmation'] + array_diff_key(
                $this->messageData,
                array_flip(['Token', 'SubscribeURL'])
            )
        );
    }

    public function testCanCreateFromRawPost()
    {
        $_SERVER['HTTP_X_AMZ_SNS_MESSAGE_TYPE'] = 'Notification';

        // Prep php://input with mocked data
        MockPhpStream::setStartingData(json_encode($this->messageData));
        stream_wrapper_unregister('php');
        stream_wrapper_register('php', __NAMESPACE__ . '\MockPhpStream');

        $message = Message::fromRawPostData();
        $this->assertInstanceOf('Aws\Sns\Message', $message);

        stream_wrapper_restore("php");
        unset($_SERVER['HTTP_X_AMZ_SNS_MESSAGE_TYPE']);
    }

    public function testCreateFromRawPostFailsWithMissingHeader()
    {
        $this->expectException(\RuntimeException::class);
        Message::fromRawPostData();
    }

    public function testCreateFromRawPostFailsWithMissingData()
    {
        $this->expectException(\RuntimeException::class);
        $_SERVER['HTTP_X_AMZ_SNS_MESSAGE_TYPE'] = 'Notification';
        Message::fromRawPostData();
        unset($_SERVER['HTTP_X_AMZ_SNS_MESSAGE_TYPE']);
    }

    public function testCanCreateFromPsr7Request()
    {
        $request = new Request(
            'POST',
            '/',
            [],
            json_encode($this->messageData)
        );
        $message = Message::fromPsrRequest($request);
        $this->assertInstanceOf('Aws\Sns\Message', $message);
    }

    public function testCreateFromPsr7RequestFailsWithMissingData()
    {
        $this->expectException(\RuntimeException::class);
        $request = new Request(
            'POST',
            '/',
            [],
            'Not valid JSON'
        );
        Message::fromPsrRequest($request);
    }

    public function testArrayAccess()
    {
        $message = new Message($this->messageData);

        $this->assertInstanceOf('ArrayAccess', $message);
        $message['foo'] = 'bar';
        $this->assertTrue(isset($message['foo']));
        $this->assertTrue($message['foo'] === 'bar');
        unset($message['foo']);
        $this->assertFalse(isset($message['foo']));
    }
}

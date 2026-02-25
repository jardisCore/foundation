<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Unit\Context;

use JardisCore\Foundation\Context\Response;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for Response
 *
 * Focus: Nested responses, error collection, data aggregation
 * Strategy: Pure PHP logic, no infrastructure needed
 */
class ResponseTest extends TestCase
{
    public function testGetContextReturnsProvidedContext(): void
    {
        $response = new Response('OrderContext');

        $this->assertSame('OrderContext', $response->getContext());
    }

    public function testAddDataStoresKeyValuePair(): void
    {
        $response = new Response('TestContext');
        $response->addData('orderId', 123);

        $data = $response->getData();

        $this->assertArrayHasKey('orderId', $data);
        $this->assertSame(123, $data['orderId']);
    }

    public function testSetDataReplacesExistingData(): void
    {
        $response = new Response('TestContext');
        $response->addData('key1', 'value1');
        $response->setData(['key2' => 'value2']);

        $data = $response->getData();

        $this->assertArrayNotHasKey('key1', $data);
        $this->assertArrayHasKey('key2', $data);
        $this->assertSame('value2', $data['key2']);
    }

    public function testGetAllDataIncludesNestedResponses(): void
    {
        $mainResponse = new Response('OrderContext');
        $mainResponse->addData('orderId', 123);

        $subResponse = new Response('InventoryContext');
        $subResponse->addData('stockLevel', 50);

        $mainResponse->addResponse($subResponse);

        $allData = $mainResponse->getAllData();

        $this->assertArrayHasKey('OrderContext', $allData);
        $this->assertArrayHasKey('InventoryContext', $allData);
        $this->assertSame(123, $allData['OrderContext']['orderId']);
        $this->assertSame(50, $allData['InventoryContext']['stockLevel']);
    }

    public function testAddEventStoresEvent(): void
    {
        $response = new Response('TestContext');
        $event = new class {
            public string $type = 'OrderCreated';
        };

        $response->addEvent($event);

        $events = $response->getEvents();

        $this->assertCount(1, $events);
        $this->assertSame($event, $events[0]);
    }

    public function testGetAllEventsIncludesNestedResponses(): void
    {
        $mainResponse = new Response('OrderContext');
        $event1 = new class {
            public string $type = 'OrderCreated';
        };
        $mainResponse->addEvent($event1);

        $subResponse = new Response('InventoryContext');
        $event2 = new class {
            public string $type = 'StockReserved';
        };
        $subResponse->addEvent($event2);

        $mainResponse->addResponse($subResponse);

        $allEvents = $mainResponse->getAllEvents();

        $this->assertCount(2, $allEvents);
        $this->assertContains($event1, $allEvents);
        $this->assertContains($event2, $allEvents);
    }

    public function testAddErrorStoresErrorMessage(): void
    {
        $response = new Response('TestContext');
        $response->addError('Validation failed');

        $errors = $response->getErrors();

        $this->assertCount(1, $errors);
        $this->assertSame('Validation failed', $errors[0]);
    }

    public function testHasErrorsReturnsTrueWhenErrorsExist(): void
    {
        $response = new Response('TestContext');

        $this->assertFalse($response->hasErrors());

        $response->addError('Some error');

        $this->assertTrue($response->hasErrors());
    }

    public function testGetAllErrorsIncludesContextKeys(): void
    {
        $mainResponse = new Response('OrderContext');
        $mainResponse->addError('Order validation failed');

        $subResponse = new Response('PaymentContext');
        $subResponse->addError('Payment declined');

        $mainResponse->addResponse($subResponse);

        $allErrors = $mainResponse->getAllErrors();

        $this->assertArrayHasKey('OrderContext', $allErrors);
        $this->assertArrayHasKey('PaymentContext', $allErrors);
        $this->assertContains('Order validation failed', $allErrors['OrderContext']);
        $this->assertContains('Payment declined', $allErrors['PaymentContext']);
    }

    public function testGetAllErrorsExcludesContextsWithoutErrors(): void
    {
        $mainResponse = new Response('OrderContext');
        $mainResponse->addError('Order validation failed');

        $subResponse = new Response('InventoryContext');
        // No errors added

        $mainResponse->addResponse($subResponse);

        $allErrors = $mainResponse->getAllErrors();

        $this->assertArrayHasKey('OrderContext', $allErrors);
        $this->assertArrayNotHasKey('InventoryContext', $allErrors);
    }

    public function testIsSuccessReturnsTrueWhenNoErrors(): void
    {
        $response = new Response('TestContext');
        $response->addData('key', 'value');

        $this->assertTrue($response->isSuccess());
    }

    public function testIsSuccessReturnsFalseWhenHasErrors(): void
    {
        $response = new Response('TestContext');
        $response->addError('Error occurred');

        $this->assertFalse($response->isSuccess());
    }

    public function testIsSuccessReturnsFalseWhenSubResponseHasErrors(): void
    {
        $mainResponse = new Response('OrderContext');

        $subResponse = new Response('PaymentContext');
        $subResponse->addError('Payment failed');

        $mainResponse->addResponse($subResponse);

        $this->assertFalse(
            $mainResponse->isSuccess(),
            'Main response should not be successful if sub-response has errors'
        );
    }

    public function testAddResponseStoresNestedResponse(): void
    {
        $mainResponse = new Response('OrderContext');
        $subResponse = new Response('InventoryContext');

        $mainResponse->addResponse($subResponse);

        $responses = $mainResponse->getResponses();

        $this->assertCount(1, $responses);
        $this->assertSame($subResponse, $responses[0]);
    }

    public function testGetMetadataReturnsResponseStatistics(): void
    {
        $response = new Response('TestContext');
        $response->addData('key', 'value');
        $response->addEvent(new class {
        });
        $response->addError('Error');

        $metadata = $response->getMetadata();

        $this->assertArrayHasKey('context', $metadata);
        $this->assertArrayHasKey('createdAt', $metadata);
        $this->assertArrayHasKey('elapsedTime', $metadata);
        $this->assertArrayHasKey('eventCount', $metadata);
        $this->assertArrayHasKey('errorCount', $metadata);
        $this->assertArrayHasKey('responseCount', $metadata);
        $this->assertArrayHasKey('hasErrors', $metadata);
        $this->assertArrayHasKey('isSuccess', $metadata);

        $this->assertSame('TestContext', $metadata['context']);
        $this->assertSame(1, $metadata['eventCount']);
        $this->assertSame(1, $metadata['errorCount']);
        $this->assertTrue($metadata['hasErrors']);
        $this->assertFalse($metadata['isSuccess']);
    }

    public function testGetAllMetadataIncludesNestedResponses(): void
    {
        $mainResponse = new Response('OrderContext');
        $subResponse = new Response('InventoryContext');

        $mainResponse->addResponse($subResponse);

        $allMetadata = $mainResponse->getAllMetadata();

        $this->assertArrayHasKey('OrderContext', $allMetadata);
        $this->assertArrayHasKey('InventoryContext', $allMetadata);
    }

    public function testElapsedTimeIncreasesOverTime(): void
    {
        $response = new Response('TestContext');

        $metadata1 = $response->getMetadata();
        usleep(1000); // 1ms
        $metadata2 = $response->getMetadata();

        $this->assertGreaterThan($metadata1['elapsedTime'], $metadata2['elapsedTime']);
    }

    public function testFluentInterfaceAllowsMethodChaining(): void
    {
        $response = new Response('TestContext');

        $result = $response
            ->addData('key1', 'value1')
            ->addData('key2', 'value2')
            ->addEvent(new class {
            })
            ->addError('Error message');

        $this->assertSame($response, $result, 'Methods should return $this for chaining');
    }
}

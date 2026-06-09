<?php

namespace Tests\Unit;

use App\Domains\Channels\Services\Mailbox\ImapMailboxReader;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class ImapMailboxReaderTest extends TestCase
{
    #[Test]
    public function test_read_tagged_response_reads_imap_literals_instead_of_treating_body_as_protocol(): void
    {
        [$server, $client] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        $literal = "--0000000000006729990653a9723c\r\nContent-Type: text/plain; charset=\"UTF-8\"\r\n\r\nHello";
        $tag = 'A1b2c3';
        $payload = "* 1 FETCH (UID 1 BODY[] {".strlen($literal)."}\r\n"
            .$literal."\r\n)\r\n"
            ."{$tag} OK UID FETCH completed\r\n";

        stream_set_blocking($server, false);
        fwrite($server, $payload);
        fclose($server);

        $reader = new ImapMailboxReader;
        $method = new ReflectionMethod(ImapMailboxReader::class, 'readTaggedResponse');
        $method->setAccessible(true);

        $response = $method->invoke($reader, $client, $tag);

        $this->assertStringContainsString($tag.' OK UID FETCH completed', $response);
        $this->assertStringContainsString('--0000000000006729990653a9723c', $response);
        $this->assertStringContainsString('Hello', $response);

        fclose($client);
    }

    #[Test]
    public function test_parse_search_uids_handles_multiline_imap_response(): void
    {
        $reader = new ImapMailboxReader;
        $method = new ReflectionMethod(ImapMailboxReader::class, 'parseSearchUids');
        $method->setAccessible(true);

        $response = "* SEARCH 76\nA7e07a7 OK SEARCH completed (Success)";

        $this->assertSame(['76'], $method->invoke($reader, $response));
    }

    #[Test]
    public function test_parse_search_uids_returns_multiple_uids(): void
    {
        $reader = new ImapMailboxReader;
        $method = new ReflectionMethod(ImapMailboxReader::class, 'parseSearchUids');
        $method->setAccessible(true);

        $response = "* SEARCH 74 75 76\nA7e07a7 OK SEARCH completed";

        $this->assertSame(['74', '75', '76'], $method->invoke($reader, $response));
    }

    #[Test]
    public function test_parse_search_uids_returns_empty_when_no_matches(): void
    {
        $reader = new ImapMailboxReader;
        $method = new ReflectionMethod(ImapMailboxReader::class, 'parseSearchUids');
        $method->setAccessible(true);

        $response = "* SEARCH\nA7e07a7 OK SEARCH completed";

        $this->assertSame([], $method->invoke($reader, $response));
    }

    #[Test]
    public function test_read_tagged_response_throws_when_command_fails(): void
    {
        [$server, $client] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        $tag = 'A1b2c3';
        fwrite($server, "{$tag} NO LOGIN failed\r\n");
        fclose($server);

        $reader = new ImapMailboxReader;
        $method = new ReflectionMethod(ImapMailboxReader::class, 'readTaggedResponse');
        $method->setAccessible(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('IMAP command failed');

        try {
            $method->invoke($reader, $client, $tag);
        } finally {
            fclose($client);
        }
    }
}

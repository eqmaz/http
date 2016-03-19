<?php

namespace React\Tests\Http\Parser;

use React\Http\FileInterface;
use React\Http\Parser\Multipart;
use React\Http\Request;
use React\Stream\ThroughStream;
use React\Tests\Http\TestCase;

class MultipartParserTest extends TestCase
{

    public function testPostKey()
    {
        $files = [];
        $post = [];

        $stream = new ThroughStream();
        $request = new Request('POST', 'http://example.com/');
        $request->on('post', function ($key, $value) use (&$post) {
            $post[$key] = $value;
        });
        $request->on('file', function (FileInterface $file) use (&$files) {
            $files[] = $file;
        });

        $boundary = "---------------------------5844729766471062541057622570";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users[one]\"\r\n";
        $data .= "\r\n";
        $data .= "single\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users[two]\"\r\n";
        $data .= "\r\n";
        $data .= "second\r\n";
        $data .= "--$boundary--\r\n";

        new Multipart($stream, $boundary, $request);
        $stream->write($data);

        $this->assertEmpty($files);
        $this->assertEquals(
            [
                'users[one]' => 'single',
                'users[two]' => 'second',
            ],
            $post
        );
    }

    public function testFileUpload()
    {
        $files = [];
        $post = [];

        $stream = new ThroughStream();
        $request = new Request('POST', 'http://example.com/');
        $request->on('post', function ($key, $value) use (&$post) {
            $post[] = [$key => $value];
        });
        $request->on('file', function (FileInterface $file) use (&$files) {
            $files[] = $file;
        });

        $file = base64_decode("R0lGODlhAQABAIAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==");

        $boundary = "---------------------------12758086162038677464950549563";

        new Multipart($stream, $boundary, $request);

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users[one]\"\r\n";
        $data .= "\r\n";
        $data .= "single\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users[two]\"\r\n";
        $data .= "\r\n";
        $data .= "second\r\n";
        $stream->write($data);
        $stream->write("--$boundary\r\n");
        $stream->write("Content-Disposition: form-data; name=\"user\"\r\n");
        $stream->write("\r\n");
        $stream->write("single\r\n");
        $stream->write("--$boundary\r\n");
        $stream->write("Content-Disposition: form-data; name=\"user2\"\r\n");
        $stream->write("\r\n");
        $stream->write("second\r\n");
        $stream->write("--$boundary\r\n");
        $stream->write("Content-Disposition: form-data; name=\"users[]\"\r\n");
        $stream->write("\r\n");
        $stream->write("first in array\r\n");
        $stream->write("--$boundary\r\n");
        $stream->write("Content-Disposition: form-data; name=\"users[]\"\r\n");
        $stream->write("\r\n");
        $stream->write("second in array\r\n");
        $stream->write("--$boundary\r\n");
        $stream->write("Content-Disposition: form-data; name=\"file\"; filename=\"User.php\"\r\n");
        $stream->write("Content-Type: text/php\r\n");
        $stream->write("\r\n");
        $stream->write("<?php echo 'User';\r\n");
        $stream->write("\r\n");
        $line = "--$boundary";
        $lines = str_split($line, round(strlen($line) / 2));
        $stream->write($lines[0]);
        $stream->write($lines[1]);
        $stream->write("\r\n");
        $stream->write("Content-Disposition: form-data; name=\"files[]\"; filename=\"blank.gif\"\r\n");
        $stream->write("Content-Type: image/gif\r\n");
        $stream->write("X-Foo-Bar: base64\r\n");
        $stream->write("\r\n");
        $stream->write($file . "\r\n");
        $stream->write("--$boundary\r\n");
        $stream->write("Content-Disposition: form-data; name=\"files[]\"; filename=\"User.php\"\r\n" .
                       "Content-Type: text/php\r\n" .
                       "\r\n" .
                       "<?php echo 'User';\r\n");
        $stream->write("\r\n");
        $stream->write("--$boundary--\r\n");

        $this->assertEquals(6, count($post));
        $this->assertEquals(
            [
                ['users[one]' => 'single'],
                ['users[two]' => 'second'],
                ['user' => 'single'],
                ['user2' => 'second'],
                ['users[]' => 'first in array'],
                ['users[]' => 'second in array'],
            ],
            $post
        );

        $this->assertEquals(3, count($files));
        $this->assertEquals('file', $files[0]->getName());
        $this->assertEquals('User.php', $files[0]->getFilename());
        $this->assertEquals('text/php', $files[0]->getType());

        $this->assertEquals('files[]', $files[1]->getName());
        $this->assertEquals('blank.gif', $files[1]->getFilename());
        $this->assertEquals('image/gif', $files[1]->getType());

        $this->assertEquals('files[]', $files[2]->getName());
        $this->assertEquals('User.php', $files[2]->getFilename());
        $this->assertEquals('text/php', $files[2]->getType());
    }
}

<?php
/**
 * run it with phpunit --group git-pre-push
 */
namespace Rebolon\Tests;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rebolon\Entity\Book;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Class to test Symfony Serializer
 *  One of the first errors i did, came from the JSON input that had a main 'book' property which is not required by the
 *  Serializer => if i remember i also don't need it in my Converter because i deserialize it once to select only the children
 *
 * Class SerializerBookTest
 * @package Rebolon\Tests
 */
class SerializerBookTest extends TestCase
{
    /**
     * @var string allow to test a correct HTTP Post with the ability of the ParamConverter to de-duplicate entity like for author in this sample
     */
    public $simpleBook = <<<JSON
{
    "title": "Zombies in western culture"
}
JSON;

    public $bookWithSerie = <<<JSON
{
    "title": "Zombies in western culture",
    "serie": {
        "id": 4,
        "name": "whatever, it won't be read"
    }
}
JSON;

    public $bookWithCollectionOfReviews = <<<JSON
{
    "title": "Zombies in western culture",
    "reviews": [{
        "id": 4,
        "content": "this book is so cool",
        "date": "2018-05-17T00:00:00+00:00",
        "username": "Joe654"
    }, {
        "id": 5,
        "content": "hey it's awesome !",
        "date": "2018-05-22T00:00:00+00:00",
        "username": "Niko9342"
    }]
}
JSON;

    public $bookOkSimpleWithAuthor = <<<JSON
{
    "title": "Zombies in western culture",
    "authors": [{
        "job": {
            "translation": "writer"
        },
        "author": {
            "firstname": "Marc", 
            "lastname": "O'Brien"
        }
    }]
}
JSON;

    /**
     * @todo test the serializer to re-use an entity
     * @var string
     */
    public $bookOkWithExistingEntities = <<<JSON
{
    "book": {
        "title": "Oh my god, how simple it is !",
        "serie": 4
    }
}
JSON;

    /**
     * @todo same as above but with more content in the related entity
     * @var string
     */
    public $bookOkWithExistingEntitiesWithFullProps = <<<JSON
{
    "book": {
        "title": "Oh my god, how simple it is !",
        "serie": {
            "id": 4,
            "name": "whatever, it won't be read"
        }
    }
}
JSON;

    /**
     * @todo need to test with wrong data
     * @var string
     */
    public $bookNoAuthor = <<<JSON
    {
        "book": {
            "title": "Oh my god, how simple it is !",
            "authors": [{
                "author": { }
            }]
        }
    }
JSON;

    /**
     * @var LoggerInterface
     */
    public $logger;

    public function setUp()
    {
        parent::setUp(); // TODO: Change the autogenerated stub

        $this->logger = $this->createMock(LoggerInterface::class);
    }

    /**
     * deserialize a simple json into a simple Book: only title props
     */
    public function testSimpleBook()
    {
        $content = $this->simpleBook;
        $expected = json_decode($content);

        $serializer = new Serializer([
            new ObjectNormalizer(),
        ], [
            new JsonEncoder(),
        ]);

        $book = $serializer->deserialize($content, Book::class, 'json', [
            'default_constructor_arguments' => [
                Book::class => ['logger' => $this->logger, ]
            ],
        ]);

        $this->assertEquals($expected->title, $book->getTitle());
    }

    /**
     * deserialize a more complex json with a serie inside the book
     */
    public function testWithSerie()
    {
        $content = $this->bookWithSerie;
        $expected = json_decode($content);

        //@todo test with: use ArrayDenormalizer when getting a list of books in json like described in slide 70 of https://speakerdeck.com/dunglas/mastering-the-symfony-serializer

        $classMetaDataFactory = new ClassMetadataFactory(
            new AnnotationLoader(
                new AnnotationReader()
            )
        );
        $objectNormalizer = new ObjectNormalizer($classMetaDataFactory, null, null, new PhpDocExtractor());
        $serializer = new Serializer([
            new DateTimeNormalizer(),
            $objectNormalizer,
        ], [
            new JsonEncoder(),
        ]);

        $book = $serializer->deserialize($content, Book::class, 'json', [
            'default_constructor_arguments' => [
                Book::class => ['logger' => $this->logger, ]
            ],
        ]);

        $this->assertEquals($expected->title, $book->getTitle());
        $this->assertEquals($expected->serie->name, $book->getSerie()->getName());

    }

    /**
     * deserialize a really more complex json with an array of serie inside the reviews property
     *
     * @group git-pre-push
     */
    public function testWithCollectionOfReview()
    {
        $content = $this->bookWithCollectionOfReviews;
        $expected = json_decode($content);

        //@todo test with: use ArrayDenormalizer when getting a list of books in json like described in slide 70 of https://speakerdeck.com/dunglas/mastering-the-symfony-serializer
        $classMetaDataFactory = new ClassMetadataFactory(
            new AnnotationLoader(
                new AnnotationReader()
            )
        );
        $objectNormalizer = new ObjectNormalizer($classMetaDataFactory, null, null, new PhpDocExtractor());
        $serializer = new Serializer([
            new ArrayDenormalizer(),
            new DateTimeNormalizer(),
            $objectNormalizer,
        ], [
            new JsonEncoder(),
        ]);

        $book = $serializer->deserialize($content, Book::class, 'json', [
            'default_constructor_arguments' => [
                Book::class => ['logger' => $this->logger, ]
            ],
        ]);

        $this->assertEquals($expected->title, $book->getTitle());
        foreach ($expected->reviews as $k => $review) {
            $this->assertEquals($review->id, $book->getReviews()[$k]->getId());
            $this->assertEquals($review->content, $book->getReviews()[$k]->getContent());
            $this->assertEquals($review->date, $book->getReviews()[$k]->getDate()->format('c'));
            $this->assertEquals($review->username, $book->getReviews()[$k]->getUsername());
        }

    }
}
<?php

namespace App\Tests;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Comment;
use Hautelook\AliceBundle\PhpUnit\RefreshDatabaseTrait;

class CommentsTest extends ApiTestCase
{
        // This trait provided by HautelookAliceBundle will take care of refreshing the database content to a known state before each test
        use RefreshDatabaseTrait;

        public function testGetCollection(): void
        {
            // The client implements Symfony HttpClient's `HttpClientInterface`, and the response `ResponseInterface`
            $response = static::createClient()->request('GET', '/comments');
    
            $this->assertResponseIsSuccessful();
            // Asserts that the returned content type is JSON-LD (the default)
            $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
    
            // Asserts that the returned JSON is a superset of this one
            $this->assertJsonContains([
                '@context' => '/contexts/Comment',
                '@id' => '/comments',
                '@type' => 'hydra:Collection',
                'hydra:totalItems' => 102,
                'hydra:view' => [
                    '@id' => '/comments?page=1',
                    '@type' => 'hydra:PartialCollectionView',
                    'hydra:first' => '/comments?page=1',
                    'hydra:last' => '/comments?page=4',
                    'hydra:next' => '/comments?page=2',
                ],
            ]);
    
            // Because test fixtures are automatically loaded between each test, you can assert on them
            $this->assertCount(30, $response->toArray()['hydra:member']);
    
            // Asserts that the returned JSON is validated by the JSON Schema generated for this resource by API Platform
            // This generated JSON Schema is also used in the OpenAPI spec!
            $this->assertMatchesResourceCollectionJsonSchema(Comment::class);
        }
    
        public function testCreatePost(): void
        {
            $response = static::createClient()->request('POST', '/comments', ['json' => [
                'description' => 'comment description'
            ]]);
    
            $this->assertResponseStatusCodeSame(201);
            $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
            $this->assertJsonContains([
                '@context' => '/contexts/Comment',
                '@type' => 'Comment',
                'description' => 'comment description'
            ]);
            $this->assertRegExp('~^/comments/\d+$~', $response->toArray()['@id']);
            $this->assertMatchesResourceItemJsonSchema(Comment::class);
        }
    
        public function testCreateInvalidPost(): void
        {
            static::createClient()->request('POST', '/comments', ['json' => [
                'description' => 2020
            ]]);
    
            $this->assertResponseStatusCodeSame(400);
            $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
    
            $this->assertJsonContains([
                '@context' => '/contexts/Error',
                '@type' => 'hydra:Error',
                'hydra:title' => 'An error occurred',
                'hydra:description' => 'The type of the "description" attribute must be "string", "integer" given.',
            ]);
        }
    
        public function testUpdatePost(): void
        {
            $client = static::createClient();
            $iri = $this->findIriBy(Comment::class, ['description' => 'comments description']);
    
            $client->request('PUT', $iri, ['json' => [
                'description' => 'updated description',
            ]]);
    
            $this->assertResponseIsSuccessful();
            $this->assertJsonContains([
                '@id' => $iri,
                'description' => 'updated description'
            ]);
        }
    
        public function testDeletePost(): void
        {
            $client = static::createClient();
            $iri = $this->findIriBy(Comment::class, ['description' => 'comment must be delete']);
    
            $client->request('DELETE', $iri);
    
            $this->assertResponseStatusCodeSame(204);
            $this->assertNull(
                static::$container->get('doctrine')->getRepository(Comment::class)->findOneBy(['description' => 'comment must be delete'])
            );
        }
    }

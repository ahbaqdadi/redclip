<?php

namespace App\Tests;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Post;
use Hautelook\AliceBundle\PhpUnit\RefreshDatabaseTrait;

class PostsTest extends ApiTestCase
{
        // This trait provided by HautelookAliceBundle will take care of refreshing the database content to a known state before each test
        use RefreshDatabaseTrait;

        public function testGetCollection(): void
        {
            // The client implements Symfony HttpClient's `HttpClientInterface`, and the response `ResponseInterface`
            $response = static::createClient()->request('GET', '/posts');
    
            $this->assertResponseIsSuccessful();
            // Asserts that the returned content type is JSON-LD (the default)
            $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
    
            // Asserts that the returned JSON is a superset of this one
            $this->assertJsonContains([
                '@context' => '/contexts/Post',
                '@id' => '/posts',
                '@type' => 'hydra:Collection',
                'hydra:totalItems' => 101,
                'hydra:view' => [
                    '@id' => '/posts?page=1',
                    '@type' => 'hydra:PartialCollectionView',
                    'hydra:first' => '/posts?page=1',
                    'hydra:last' => '/posts?page=4',
                    'hydra:next' => '/posts?page=2',
                ],
            ]);
    
            // Because test fixtures are automatically loaded between each test, you can assert on them
            $this->assertCount(30, $response->toArray()['hydra:member']);
    
            // Asserts that the returned JSON is validated by the JSON Schema generated for this resource by API Platform
            // This generated JSON Schema is also used in the OpenAPI spec!
            $this->assertMatchesResourceCollectionJsonSchema(Post::class);
        }
    
        public function testCreatePost(): void
        {
            $response = static::createClient()->request('POST', '/posts', ['json' => [
                'title' => 'post title',
                'text' => 'post text'
            ]]);
    
            $this->assertResponseStatusCodeSame(201);
            $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
            $this->assertJsonContains([
                '@context' => '/contexts/Post',
                '@type' => 'Post',
                'title' => 'post title',
                'text' => 'post text'
            ]);
            $this->assertRegExp('~^/posts/\d+$~', $response->toArray()['@id']);
            $this->assertMatchesResourceItemJsonSchema(Post::class);
        }
    
        public function testCreateInvalidPost(): void
        {
            static::createClient()->request('POST', '/posts', ['json' => [
                'title' => 2020,
            ]]);
    
            $this->assertResponseStatusCodeSame(400);
            $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
    
            $this->assertJsonContains([
                '@context' => '/contexts/Error',
                '@type' => 'hydra:Error',
                'hydra:title' => 'An error occurred',
                'hydra:description' => 'The type of the "title" attribute must be "string", "integer" given.',
            ]);
        }
    
        public function testUpdatePost(): void
        {
            $client = static::createClient();
            $iri = $this->findIriBy(Post::class, ['title' => 'post title']);
    
            $client->request('PUT', $iri, ['json' => [
                'title' => 'updated title',
            ]]);
    
            $this->assertResponseIsSuccessful();
            $this->assertJsonContains([
                '@id' => $iri,
                'title' => 'updated title',
                'text' => 'post text'
            ]);
        }
    
        public function testDeletePost(): void
        {
            $client = static::createClient();
            $iri = $this->findIriBy(Post::class, ['text' => 'post text']);

            $client->request('DELETE', $iri);
    
            $this->assertResponseStatusCodeSame(204);
            $this->assertNull(
                static::$container->get('doctrine')->getRepository(Post::class)->findOneBy(['text' => 'post text'])
            );
        }
    }

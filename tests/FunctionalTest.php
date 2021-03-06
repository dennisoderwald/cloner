<?php

// Deps
use Bkwld\Cloner\Cloner;
use Bkwld\Cloner\Adapters\Upchuck;
use Bkwld\Cloner\Stubs\Article;
use Bkwld\Cloner\Stubs\Author;
use Bkwld\Cloner\Stubs\Photo;
use Bkwld\Upchuck\Helpers;
use Bkwld\Upchuck\Storage;
use Illuminate\Database\Capsule\Manager as DB;
use League\Flysystem\Filesystem;
use League\Flysystem\Vfs\VfsAdapter as Adapter;
use League\Flysystem\MountManager;
use VirtualFileSystem\FileSystem as Vfs;

class FunctionalTest extends PHPUnit_Framework_TestCase {

	protected function initUpchuck() {

		// Setup filesystem
		$fs = new Vfs;
		$this->fs_path = $fs->path('/');
		$this->disk = new Filesystem(new Adapter($fs));

		// Create upchuck adapter instance

		$this->helpers = new Helpers([
			'url_prefix' => '/uploads/'
		]);

		$manager = new MountManager([
			'tmp' => $this->disk,
			'disk' => $this->disk,
		]);

		$storage = new Storage($manager, $this->helpers);

		$this->upchuck = new Upchuck(
			$this->helpers,
			$storage,
			$this->disk
		);

	}

	// https://github.com/laracasts/TestDummy/blob/master/tests/FactoryTest.php#L18
	protected function setUpDatabase() {
		$db = new DB;

		$db->addConnection([
				'driver' => 'sqlite',
				'database' => ':memory:'
		]);

		$db->bootEloquent();
		$db->setAsGlobal();
	}

	// https://github.com/laracasts/TestDummy/blob/master/tests/FactoryTest.php#L31
	protected function migrateTables() {
		DB::schema()->create('articles', function ($table) {
			$table->increments('id');
			$table->string('title');
			$table->timestamps();
		});

		DB::schema()->create('authors', function ($table) {
			$table->increments('id');
			$table->string('name');
			$table->timestamps();
		});

		DB::schema()->create('article_author', function ($table) {
			$table->increments('id');
			$table->integer('article_id')->unsigned();
			$table->integer('author_id')->unsigned();
		});

		DB::schema()->create('photos', function ($table) {
			$table->increments('id');
			$table->integer('article_id')->unsigned();
			$table->string('uid');
			$table->string('image');
			$table->boolean('source')->nullable();
			$table->timestamps();
		});
	}

	protected function seed() {
		Article::unguard();
		$this->article = Article::create([
			'title' => 'Test',
		]);

		Author::unguard();
		$this->article->authors()->attach(Author::create([
			'name' => 'Steve',
		]));

		$this->disk->write('test.jpg', 'contents');

		Photo::unguard();
		$this->article->photos()->save(new Photo([
			'uid' => 1,
			'image' => '/uploads/test.jpg',
			'source' => true,
		]));
	}

	function testExists() {
		$this->initUpchuck();
		$this->setUpDatabase();
		$this->migrateTables();
		$this->seed();

		$cloner = new Cloner($this->upchuck);
		$clone = $cloner->duplicate($this->article);

		// Test that the new article was created
		$this->assertTrue($clone->exists);
		$this->assertEquals(2, $clone->id);
		$this->assertEquals('Test', $clone->title);

		// Test mamny to many
		$this->assertEquals(1, $clone->authors()->count());
		$this->assertEquals('Steve', $clone->authors()->first()->name);
		$this->assertEquals(2, DB::table('article_author')->count());

		// Test one to many
		$this->assertEquals(1, $clone->photos()->count());
		$photo = $clone->photos()->first();

		// Test excemptions
		$this->assertNull($photo->source);

		// Test callbacks
		$this->assertNotEquals(1, $photo->uid);

		// Test the file was created in a different place
		$this->assertNotEquals('/uploads/test.jpg', $photo->image);

		// Test that the file is the same
		$path = $this->helpers->path($photo->image);
		$this->assertTrue($this->disk->has($path));
		$this->assertEquals('contents', $this->disk->read($path));
	}
}
<?php

namespace Sokil\Mongo;

class CollectionTest extends \PHPUnit_Framework_TestCase
{
    /**
     *
     * @var \Sokil\Mongo\Database
     */
    private $database;
    
    /**
     *
     * @var \Sokil\Mongo\Collection
     */
    private $collection;
    
    public function setUp()
    {
        // connect to mongo
        $client = new Client('mongodb://127.0.0.1');
        
        // select database
        $this->database = $client->getDatabase('test');
        $this->collection = $this->database->getCollection('phpmongo_test_collection');
    }
    
    public function tearDown() 
    {
        $this->collection->delete();
    }
    
    public function testGetDocument()
    {
        // create document
        $document = $this->collection
            ->createDocument(array('param' => 'value'))
            ->save();   
        
        // get document
        $foundDocument = $this->collection->getDocument($document->getId());
        $this->assertEquals($document->getId(), $foundDocument->getId());

        // get document as property of collection
        $foundDocument = $this->collection->{$document->getId()};
        $this->assertEquals($document->getId(), $foundDocument->getId());
    }
    
    public function testGetDocumentByStringId()
    {        
        $document = $this->collection
            ->createDocument(array(
                '_id'   => 'abcdef',
                'param' => 'value'
            ))
            ->save();
        
        // get document
        $foundDocument = $this->collection->getDocument('abcdef');
        
        $this->assertNotNull($foundDocument);
        
        $this->assertEquals($document->getId(), $foundDocument->getId());
    }
    
    public function testGetDocuments()
    {
        // create document1
        $document1 = $this->collection
            ->createDocument(array('param' => 'value1'))
            ->save();   
        
        // create document 2
        $document2 = $this->collection
            ->createDocument(array('param' => 'value2'))
            ->save();   
        
        // get documents
        $foundDocuments = $this->collection->getDocuments(array(
            $document1->getId(),
            $document2->getId()
        ));
        
        $this->assertEquals(2, count($foundDocuments));
        
        $this->assertArrayHasKey((string) $document1->getId(), $foundDocuments);
        $this->assertArrayHasKey((string) $document2->getId(), $foundDocuments);
    }

    public function testGetDocuments_UnexistedIdsSpecified()
    {
        // get documents when wrong id's
        $this->assertEquals(array(), $this->collection->getDocuments(array(
            new \MongoId,
            new \MongoId,
            new \MongoId,
        )));
    }
    
    public function testSaveValidNewDocument()
    {
        // create document
        $document = $this->getMock('\Sokil\Mongo\Document', array('rules'), array($this->collection));
        $document
            ->expects($this->any())
            ->method('rules')
            ->will($this->returnValue(array(
                array('some-field-name', 'required')
            )));
        
        $document->set('some-field-name', 'some-value')->save();
    }
    
    public function testUpdateExistedDocument()
    {
        // create document
        $document = $this->collection
            ->createDocument(array('param' => 'value'))
            ->save();   
        
        // update document
        $document->set('param', 'new-value')->save();
        
        // test
        $document = $this->collection->getDocument($document->getId());
        $this->assertEquals('new-value', $document->param);
    }
    
    /**
     * @expectedException \Sokil\Mongo\Document\Exception\Validate
     */
    public function testSaveInvalidNewDocument()
    {
        // create document
        $document = $this->getMock('\Sokil\Mongo\Document', array('rules'), array($this->collection));
        $document
            ->expects($this->any())
            ->method('rules')
            ->will($this->returnValue(array(
                array('some-field-name', 'required')
            )));
        
        // save document
        $this->collection->saveDocument($document);
    }

    public function testDeleteCollection_UnexistedCollection()
    {
        $this->collection = $this->database->getCollection('UNEXISTED_COLLECTION_NAME');
        $this->collection->delete();
    }

    /**
     * @expectedException \Sokil\Mongo\Exception
     * @expectedExceptionMessage Error deleting collection phpmongo_test_collection: Some strange error
     */
    public function testDeleteCollection_ExceptionOnCollectionDeleteError()
    {
        $this->collectionMock = $this->getMock(
            '\MongoCollection',
            array('drop'),
            array($this->database->getMongoDB(), 'phpmongo_test_collection')
        );

        $this->collectionMock
            ->expects($this->once())
            ->method('drop')
            ->will($this->returnValue(array(
                'ok' => (double) 0,
                'errmsg' => 'Some strange error',
            )));

        $collection = new Collection($this->database, $this->collectionMock);
        $collection->delete();
    }

    public function testDeleteDocuments()
    {        
        // add
        $this->collection->createDocument(array('param' => 1))->save();
        $this->collection->createDocument(array('param' => 2))->save();
        $this->collection->createDocument(array('param' => 3))->save();
        $this->collection->createDocument(array('param' => 4))->save();
        
        // delete
        $this->collection->deleteDocuments($this->collection->expression()->whereGreater('param', 2));
        
        // test
        $this->assertEquals(2, count($this->collection));
    }

    /**
     * @expectedException \Sokil\Mongo\Exception
     * @expectedExceptionMessage Delete document error: Some strange error
     */
    public function testDeleteDocument_ErrorDeletingDocument()
    {
        $this->collectionMock = $this->getMock(
            '\MongoCollection',
            array('remove'),
            array($this->database->getMongoDB(), 'phpmongo_test_collection')
        );

        $this->collectionMock
            ->expects($this->once())
            ->method('remove')
            ->will($this->returnValue(array(
                'ok' => (double) 0,
                'err' => 'Some strange error',
            )));

        $this->collection = new Collection($this->database, $this->collectionMock);

        $document = $this->collection
            ->createDocument(array('param' => 'value'))
            ->save();

        $this->collection->deleteDocument($document);
    }

    /**
     * @expectedException \Sokil\Mongo\Exception
     * @expectedExceptionMessage Error removing documents from collection: Some strange error
     */
    public function testDeleteDocuments_ErrorDeletingDocuments()
    {
        $this->collectionMock = $this->getMock(
            '\MongoCollection',
            array('remove'),
            array($this->database->getMongoDB(), 'phpmongo_test_collection')
        );

        $this->collectionMock
            ->expects($this->once())
            ->method('remove')
            ->will($this->returnValue(array(
                'ok' => (double) 0,
                'err' => 'Some strange error',
            )));

        $this->collection = new Collection($this->database, $this->collectionMock);

        $this->collection
            ->createDocument(array('param' => 'value'))
            ->save();

        $this->collection->deleteDocuments(
            $this->collection->expression()
                ->where('param', 'value')
        );
    }

    public function testUpdateMultiple_WithAcknowledgedWriteConcern()
    {
        // get collection
        $this->collection = $this->database
            ->getCollection('phpmongo_test_collection')
            ->delete()
            ->setWriteConcern(1);
        
        // create documents
        $d1 = $this->collection->createDocument(array('p' => 1));
        $this->collection->saveDocument($d1);
        
        $d2 = $this->collection->createDocument(array('p' => 1));
        $this->collection->saveDocument($d2);
        
        // packet update
        $this->collection->updateMultiple(
            $this->collection->expression()->where('p', 1),
            $this->collection->operator()->set('k', 'v')
        );
        
        // test
        foreach($this->collection->find() as $document) {
            $this->assertArrayHasKey('k', $document->toArray());
        }
    }

    public function testUpdateMultiple_WithUnacknowledgedWriteConcern()
    {
        // get collection
        $this->collection->setUnacknowledgedWriteConcern();

        // create documents
        $d1 = $this->collection->createDocument(array('p' => 1));
        $this->collection->saveDocument($d1);

        $d2 = $this->collection->createDocument(array('p' => 1));
        $this->collection->saveDocument($d2);

        // packet update
        $this->collection->updateMultiple(
            $this->collection->expression()->where('p', 1),
            $this->collection->operator()->set('k', 'v')
        );

        // test
        foreach($this->collection->find() as $document) {
            $this->assertArrayHasKey('k', $document->toArray());
        }
    }
    
    public function testUpdateAll_WithAcknowledgedWriteConcern()
    {
        // get collection
        $this->collection->setWriteConcern(1);
        
        // create documents
        $d1 = $this->collection->createDocument(array('p' => 1));
        $this->collection->saveDocument($d1);
        
        $d2 = $this->collection->createDocument(array('p' => 1));
        $this->collection->saveDocument($d2);
        
        // packet update
        $this->collection->updateAll(
            $this->collection->operator()->set('k', 'v')
        );
        
        // test
        foreach($this->collection->find() as $document) {
            $this->assertArrayHasKey('k', $document->toArray());
        }
    }

    public function testUpdateAll_WithUnacknowledgedWriteConcern()
    {
        // get collection
        $this->collection->setUnacknowledgedWriteConcern();

        // create documents
        $d1 = $this->collection->createDocument(array('p' => 1));
        $this->collection->saveDocument($d1);

        $d2 = $this->collection->createDocument(array('p' => 1));
        $this->collection->saveDocument($d2);

        // packet update
        $this->collection->updateAll(
            $this->collection->operator()->set('k', 'v')
        );

        // test
        foreach($this->collection->find() as $document) {
            $this->assertArrayHasKey('k', $document->toArray());
        }
    }

    /**
     * @expectedException \Sokil\Mongo\Exception
     * @expectedExceptionMessage Multiple update error: some_strange_error: Some strange error
     */
    public function testUpdateMultiple_ErrorWithWriteConcern()
    {
        // mock mongo's collection
        $mongoCollectionMock = $this->getMock(
            '\MongoCollection',
            array('update'),
            array($this->database->getMongoDB(), 'phpmongo_test_collection')
        );

        $mongoCollectionMock
            ->expects($this->once())
            ->method('update')
            ->with(
                $this->isType('array'),
                $this->isType('array'),
                $this->arrayHasKey('multiple')
            )
            ->will($this->returnValue(array(
                'ok' => (double) 0,
                'err' => 'some_strange_error',
                'errmsg' => 'Some strange error',
            )));

        // create collection with mocked original mongo collection
        $this->collection = new Collection($this->database, $mongoCollectionMock);
        $this->collection->setWriteConcern(1);

        $this->collection->updateMultiple(new Expression(), new Operator());
    }

    /**
     * @expectedException \Sokil\Mongo\Exception
     * @expectedExceptionMessage Multiple update error
     */
    public function testUpdateMultiple_ErrorWithUnacknowledgedWriteConcern()
    {
        // mock mongo's collection
        $mongoCollectionMock = $this->getMock(
            '\MongoCollection',
            array('update'),
            array($this->database->getMongoDB(), 'phpmongo_test_collection')
        );

        $mongoCollectionMock
            ->expects($this->once())
            ->method('update')
            ->with(
                $this->isType('array'),
                $this->isType('array'),
                $this->arrayHasKey('multiple')
            )
            ->will($this->returnValue(false));

        // create collection with mocked original mongo collection
        $this->collection = new Collection($this->database, $mongoCollectionMock);
        $this->collection->setUnacknowledgedWriteConcern();

        $this->collection->updateMultiple(new Expression(), new Operator());
    }

    /**
     * @expectedException \Sokil\Mongo\Exception
     * @expectedExceptionMessage Update error: some_strange_error: Some strange error
     */
    public function testUpdateAll_ErrorWithWriteConcern()
    {
        // mock mongo's collection
        $mongoCollectionMock = $this->getMock(
            '\MongoCollection',
            array('update'),
            array($this->database->getMongoDB(), 'phpmongo_test_collection')
        );

        $mongoCollectionMock
            ->expects($this->once())
            ->method('update')
            ->with(
                $this->isType('array'),
                $this->isType('array'),
                $this->arrayHasKey('multiple')
            )
            ->will($this->returnValue(array(
                'ok' => (double) 0,
                'err' => 'some_strange_error',
                'errmsg' => 'Some strange error',
            )));

        // create collection with mocked original mongo collection
        $this->collection = new Collection($this->database, $mongoCollectionMock);
        $this->collection->setWriteConcern(1);

        $this->collection->updateAll(new Operator());
    }

    /**
     * @expectedException \Sokil\Mongo\Exception
     * @expectedExceptionMessage Update error
     */
    public function testUpdateAll_ErrorWithUnacknowledgedWriteConcern()
    {
        // mock mongo's collection
        $mongoCollectionMock = $this->getMock(
            '\MongoCollection',
            array('update'),
            array($this->database->getMongoDB(), 'phpmongo_test_collection')
        );

        $mongoCollectionMock
            ->expects($this->once())
            ->method('update')
            ->with(
                $this->isType('array'),
                $this->isType('array'),
                $this->arrayHasKey('multiple')
            )
            ->will($this->returnValue(false));

        // create collection with mocked original mongo collection
        $this->collection = new Collection($this->database, $mongoCollectionMock);
        $this->collection->setUnacknowledgedWriteConcern();

        $this->collection->updateAll(new Operator());
    }

    public function testEnableDocumentPool()
    {
        
        $this->collection->clearDocumentPool();

        // disable document pool
        $this->collection->disableDocumentPool();
        $this->assertFalse($this->collection->isDocumentPoolEnabled());

        // create documents
        $document = $this->collection
            ->createDocument(array(
                'k' => array(
                    'f'     => 'F1',
                    'kk'    => 'A',
                )
            ))
            ->save();

        // read document
        $this->collection->getDocument($document->getId());

        // check if document in pool
        $this->assertTrue($this->collection->isDocumentPoolEmpty());

        // enable document pool
        $this->collection->enableDocumentPool();
        $this->assertTrue($this->collection->isDocumentPoolEnabled());

        // read document to pool
        $this->collection->getDocument($document->getId());

        // check if document in pool
        $this->assertFalse($this->collection->isDocumentPoolEmpty());

        // clear document pool
        $this->collection->clearDocumentPool();
        $this->assertTrue($this->collection->isDocumentPoolEmpty());

        // disable document pool
        $this->collection->disableDocumentPool();
        $this->assertFalse($this->collection->isDocumentPoolEnabled());
    }

    public function testGetDistinct()
    {
        
    
        // create documents
        $this->collection
            ->createDocument(array(
                'k' => array(
                    'f'     => 'F1',
                    'kk'    => 'A',
                )
            ))
            ->save();
        
        $this->collection
            ->createDocument(array(
                'k' => array(
                    'f'     => 'F1',
                    'kk'    => 'A',
                )
            ))
            ->save();
        
        $this->collection
            ->createDocument(array(
                'k' => array(
                    'f'     => 'F1',
                    'kk'    => 'B',
                )
            ))
            ->save();
        
        $this->collection
            ->createDocument(array(
                'k' => array(
                    'f'     => 'F2',
                    'kk'    => 'C',
                )
            ))
            ->save();
        
        // get distinkt
        $distinctValues = $this->collection
            ->getDistinct('k.kk', $this->collection->expression()->where('k.f', 'F1'));
        
        $this->assertEquals(array('A', 'B'), $distinctValues);
    }

    public function testGetDistinctWithoutExpression()
    {
        

        // create documents
        $this->collection
            ->createDocument(array(
                'k' => array(
                    'f'     => 'F1',
                    'kk'    => 'A',
                )
            ))
            ->save();

        $this->collection
            ->createDocument(array(
                'k' => array(
                    'f'     => 'F1',
                    'kk'    => 'A',
                )
            ))
            ->save();

        $this->collection
            ->createDocument(array(
                'k' => array(
                    'f'     => 'F1',
                    'kk'    => 'B',
                )
            ))
            ->save();

        $this->collection
            ->createDocument(array(
                'k' => array(
                    'f'     => 'F2',
                    'kk'    => 'C',
                )
            ))
            ->save();

        // get distinct
        $distinctValues = $this->collection
            ->getDistinct('k.kk');

        $this->assertEquals(array('A', 'B', 'C'), $distinctValues);
    }
    
    public function testInsertMultiple_Acknowledged()
    {
        $this->collection->setWriteConcern(1);
        
        $this->collection
            ->insertMultiple(array(
                array('a' => 1, 'b' => 2),
                array('a' => 3, 'b' => 4),
            ));
        
        $document = $this->collection->find()->where('a', 1)->findOne();
        
        $this->assertNotEmpty($document);
        
        $this->assertEquals(2, $document->b);
    }

    public function testInsertMultiple_Unacknovledged()
    {
        $this->collection->setUnacknowledgedWriteConcern();

        $this->collection
            ->insertMultiple(array(
                array('a' => 1, 'b' => 2),
                array('a' => 3, 'b' => 4),
            ));

        $document = $this->collection->find()->where('a', 1)->findOne();

        $this->assertNotEmpty($document);

        $this->assertEquals(2, $document->b);
    }

    /**
     * @expectedException \Sokil\Mongo\Document\Exception\Validate
     * @expectedExceptionMessage Document invalid
     */
    public function testInsertMultiple_ValidateError()
    {
        // mock collection
        $this->collectionMock = $this->getMock(
            '\Sokil\Mongo\Collection',
            array('createDocument'),
            array($this->database, 'phpmongo_test_collection')
        );

        // mock document
        $documentMock = $this->getMock(
            'Sokil\Mongo\Document',
            array('rules'),
            array($this->collectionMock)
        );

        // implement validation rules
        $documentMock
            ->expects($this->any())
            ->method('rules')
            ->will($this->returnValue(array(
                array('a', 'email'),
            )));

        // replace creating document with mocked
        $this->collectionMock
            ->expects($this->once())
            ->method('createDocument')
            ->will($this->returnValue($documentMock));

        // insert multiple
        $this->collectionMock->insertMultiple(array(
            array('a' => 1, 'b' => 2),
            array('a' => 3, 'b' => 4),
        ));
    }

    /**
     * @expectedException \Sokil\Mongo\Exception
     * @expectedExceptionMessage Batch insert error: Some strange error
     */
    public function testInsertMultiple_ErrorInsertingWithAcknowledgeWrite()
    {
        $this->collectionMock = $this->getMock(
            '\MongoCollection',
            array('batchInsert'),
            array($this->database->getMongoDB(), 'phpmongo_test_collection')
        );

        $this->collectionMock
            ->expects($this->once())
            ->method('batchInsert')
            ->will($this->returnValue(array(
                'ok' => (double) 0,
                'err' => 'Some strange error',
            )));

        $collection = new Collection($this->database, $this->collectionMock);

        // insert multiple
        $collection->insertMultiple(array(
            array('a' => 1, 'b' => 2),
            array('a' => 3, 'b' => 4),
        ));
    }

    /**
     * @expectedException \Sokil\Mongo\Exception
     * @expectedExceptionMessage Batch insert error
     */
    public function testInsertMultiple_ErrorInsertingWithUnacknowledgeWrite()
    {
        $this->collectionMock = $this->getMock(
            '\MongoCollection',
            array('batchInsert'),
            array($this->database->getMongoDB(), 'phpmongo_test_collection')
        );

        $this->collectionMock
            ->expects($this->once())
            ->method('batchInsert')
            ->will($this->returnValue(false));

        $this->collection = new Collection($this->database, $this->collectionMock);

        // insert multiple
        $this->collection->insertMultiple(array(
            array('a' => 1, 'b' => 2),
            array('a' => 3, 'b' => 4),
        ));
    }
    
    public function testInsert_Acknowledged()
    {
        $this->collection->setWriteConcern(1);
        
        $this->collection->insert(array('a' => 1, 'b' => 2));
        
        $document = $this->collection->find()->where('a', 1)->findOne();
        
        $this->assertNotEmpty($document);
        
        $this->assertEquals(2, $document->b);
    }

    /**
     * @expectedException \Sokil\Mongo\Exception
     * @expectedExceptionMessage Insert error: some_error: Some strange error
     */
    public function testInsert_Acknowledged_Error()
    {
        $this->collectionMock = $this->getMock(
            '\MongoCollection',
            array('insert'),
            array($this->database->getMongoDB(), 'phpmongo_test_collection')
        );

        $this->collectionMock
            ->expects($this->once())
            ->method('insert')
            ->will($this->returnValue(array(
                'ok' => (double) 0,
                'err' => 'some_error',
                'errmsg' => 'Some strange error',
            )));

        $collection = new Collection($this->database, $this->collectionMock);
        $collection->setWriteConcern(1);

        $collection->insert(array('a' => 1, 'b' => 2));
    }

    public function testInsert_Unacknowledged()
    {
        $this->collection->setUnacknowledgedWriteConcern();

        $this->collection->insert(array('a' => 1, 'b' => 2));

        $document = $this->collection->find()->where('a', 1)->findOne();

        $this->assertNotEmpty($document);

        $this->assertEquals(2, $document->b);
    }

    /**
     * @expectedException \Sokil\Mongo\Exception
     * @expectedExceptionMessage Insert error
     */
    public function testInsert_Unacknowledged_Error()
    {
        $this->collectionMock = $this->getMock(
            '\MongoCollection',
            array('insert'),
            array($this->database->getMongoDB(), 'phpmongo_test_collection')
        );

        $this->collectionMock
            ->expects($this->once())
            ->method('insert')
            ->will($this->returnValue(false));

        $collection = new Collection($this->database, $this->collectionMock);
        $collection->setUnacknowledgedWriteConcern();

        $collection->insert(array('a' => 1, 'b' => 2));
    }
    
    /**
     * @expectedException \Sokil\Mongo\Exception
     * @expectedExceptionMessage ns not found
     */
    public function testValidateOnNotExistedCollection()
    {
        $this->database
            ->getCollection('phpmongo_unexisted_collection')
            ->validate(true);
    }
    
    public function testValidateOnExistedCollection()
    {        
        $this->collection->createDocument(array('param' => 1))->save();
       
        $result = $this->collection->validate(true);
        
        $this->assertInternalType('array', $result);
    }
    
    public function testCappedCollectionInsert()
    {
        $this->collection = $this->database
            ->createCappedCollection('capped_collection', 3, 30);
        
        $this->collection->createDocument(array('param' => 1))->save();
        $this->collection->createDocument(array('param' => 2))->save();
        $this->collection->createDocument(array('param' => 3))->save();
        $this->collection->createDocument(array('param' => 4))->save();
        
        $this->assertEquals(3, $this->collection->find()->count());
        
        $documents = $this->collection->find();   
        
        $this->assertEquals(2, $documents->current()->param);
        
        $documents->next();
        $this->assertEquals(3, $documents->current()->param);
        
        $documents->next();
        $this->assertEquals(4, $documents->current()->param);
        
        $this->collection->delete();
    }
    
    public function testStats()
    {
        $stats = $this->collection->stats();
        var_dump($stats);
        
        $this->assertEquals((double) 0, $stats['ok']);
        $this->assertEquals('Collection [test.phpmongo_test_collection] not found.', $stats['errmsg']);
    }

    public function testFind()
    {
        $d1 = $this->collection->createDocument(array('param' => 1))->save();
        $d2 = $this->collection->createDocument(array('param' => 2))->save();
        $d3 = $this->collection->createDocument(array('param' => 3))->save();
        $d4 = $this->collection->createDocument(array('param' => 4))->save();

        $queryBuilder = $this->collection->find(function(\Sokil\Mongo\Expression $expression) {
            $expression->where('param', 3);
        });

        $this->assertEquals($d3->getId(), $queryBuilder->findOne()->getId());
    }
    
    public function testAggregate()
    {            
        $this->collection->createDocument(array('param' => 1))->save();
        $this->collection->createDocument(array('param' => 2))->save();
        $this->collection->createDocument(array('param' => 3))->save();
        $this->collection->createDocument(array('param' => 4))->save();
        
        $result = $this->collection->createPipeline()
            ->match(array('param' => array('$gte' => 2)))
            ->group(array('_id' => 0, 'sum' => array('$sum' => '$param')))
            ->aggregate();
        
        $this->assertEquals(9, $result[0]['sum']);
        
    }

    /**
     * @expectedException \Sokil\Mongo\Exception
     * @expectedExceptionMessage Wrong pipelines specified
     */
    public function testAggregate_WrongArgument()
    {
        $this->collection->aggregate('hello');
    }

    /**
     * @expectedException \Sokil\Mongo\Exception
     * @expectedExceptionMessage Aggregate error: some_error
     */
    public function testAggregate_ServerSideError()
    {
        $mongoDatabaseMock = $this->getMock(
            '\MongoDB',
            array('command'),
            array($this->database->getClient()->getConnection(), 'test')
        );

        $mongoDatabaseMock
            ->expects($this->once())
            ->method('command')
            ->will($this->returnValue(array(
                'ok' => (double) 0,
                'errmsg' => 'some_error',
                'code' => 1785342,
            )));

        $database = new Database($this->database->getClient(), $mongoDatabaseMock);
        $database
            ->getCollection('phpmongo_test_collection')
            ->aggregate(array(
                array('$match' => array('field' => 'value'))
            ));
    }

    public function testLogAggregateResults()
    {
        // create documents
        $this->collection->createDocument(array('param' => 1))->save();
        $this->collection->createDocument(array('param' => 2))->save();
        $this->collection->createDocument(array('param' => 3))->save();
        $this->collection->createDocument(array('param' => 4))->save();

        // create logger
        $logger = $this->getMock('\Psr\Log\LoggerInterface');
        $logger
            ->expects($this->once())
            ->method('debug')
            ->with('Sokil\Mongo\Collection:<br><b>Pipelines</b>:<br>[{"$match":{"param":{"$gte":2}}},{"$group":{"_id":0,"sum":{"$sum":"$param"}}}]');

        // set logger to client
        $this->database->getClient()->setLogger($logger);

        // aggregate
        $this->collection
            ->createPipeline()
            ->match(array('param' => array('$gte' => 2)))
            ->group(array('_id' => 0, 'sum' => array('$sum' => '$param')))
            ->aggregate();
    }
    
    public function testExplainAggregate()
    {            
        $this->collection->createDocument(array('param' => 1))->save();
        $this->collection->createDocument(array('param' => 2))->save();
        $this->collection->createDocument(array('param' => 3))->save();
        $this->collection->createDocument(array('param' => 4))->save();
        
        $pipelines = $this->collection->createPipeline()
            ->match(array('param' => array('$gte' => 2)))
            ->group(array('_id' => 0, 'sum' => array('$sum' => '$param')));
        
        try {
            $explain = $this->collection->explainAggregate($pipelines);
            $this->assertArrayHasKey('stages', $explain);
        } catch (\Exception $e) {
            $this->assertEquals('Explain of aggregation implemented only from 2.6.0', $e->getMessage());
        }
        
    }

    /**
     * @expectedException \Sokil\Mongo\Exception
     * @expectedExceptionMessage Explain of aggregation implemented only from 2.6.0
     */
    public function testExplainAggregate_UnsupportedDbVersion()
    {
        // define db version where aggregate explanation supported
        $clientMock = $this->getMock(
            '\Sokil\Mongo\Client',
            array('getDbVersion')
        );

        $clientMock
            ->expects($this->once())
            ->method('getDbVersion')
            ->will($this->returnValue('2.4.0'));

        $clientMock->setConnection($this->database->getClient()->getConnection());

        $clientMock
            ->getDatabase('test')
            ->getCollection('phpmongo_test_collection')
            ->explainAggregate(array());
    }

    /**
     * @expectedException \Sokil\Mongo\Exception
     * @expectedExceptionMessage Wrong pipelines specified
     */
    public function testExplainAggregate_WrongArgument()
    {
        // define db version where aggregate explanation supported
        $clientMock = $this->getMock(
            '\Sokil\Mongo\Client',
            array('getDbVersion')
        );

        $clientMock
            ->expects($this->once())
            ->method('getDbVersion')
            ->will($this->returnValue('2.6.0'));

        $clientMock->setConnection($this->database->getClient()->getConnection());

        $this->collection = $clientMock
            ->getDatabase('test')
            ->getCollection('phpmongo_test_collection')
            ->explainAggregate('wrong_argument');
    }

    public function testReadPrimaryOnly()
    {
        $this->collection->readPrimaryOnly();

        $this->assertEquals(array(
            'type' => \MongoClient::RP_PRIMARY
        ), $this->collection->getReadPreference());
    }

    public function testReadPrimaryPreferred()
    {
        $this->collection->readPrimaryPreferred(array(
            array('dc' => 'kyiv'),
            array('dc' => 'lviv'),
        ));

        $this->assertEquals(array(
            'type' => \MongoClient::RP_PRIMARY_PREFERRED,
            'tagsets' => array(
                array('dc' => 'kyiv'),
                array('dc' => 'lviv'),
            ),
        ), $this->collection->getReadPreference());
    }

    public function testReadSecondaryOnly(array $tags = null)
    {
        $this->collection->readSecondaryOnly(array(
            array('dc' => 'kyiv'),
            array('dc' => 'lviv'),
        ));

        $this->assertEquals(array(
            'type' => \MongoClient::RP_SECONDARY,
            'tagsets' => array(
                array('dc' => 'kyiv'),
                array('dc' => 'lviv'),
            ),
        ), $this->collection->getReadPreference());
    }

    public function testReadSecondaryPreferred(array $tags = null)
    {
        $this->collection->readSecondaryPreferred(array(
            array('dc' => 'kyiv'),
            array('dc' => 'lviv'),
        ));

        $this->assertEquals(array(
            'type' => \MongoClient::RP_SECONDARY_PREFERRED,
            'tagsets' => array(
                array('dc' => 'kyiv'),
                array('dc' => 'lviv'),
            ),
        ), $this->collection->getReadPreference());
    }

    public function testReadNearest(array $tags = null)
    {
        $this->collection->readNearest(array(
            array('dc' => 'kyiv'),
            array('dc' => 'lviv'),
        ));

        $this->assertEquals(array(
            'type' => \MongoClient::RP_NEAREST,
            'tagsets' => array(
                array('dc' => 'kyiv'),
                array('dc' => 'lviv'),
            ),
        ), $this->collection->getReadPreference());
    }

    public function testSetWriteConcern()
    {
        $this->collection->setWriteConcern('majority', 12000);

        $this->assertEquals(array(
            'w' => 'majority',
            'wtimeout' => 12000
        ), $this->collection->getWriteConcern());
    }

    /**
     * @expectedException \Sokil\Mongo\Exception
     * @expectedExceptionMessage Error setting write concern
     */
    public function testSetWriteConcern_Error()
    {
        $mongoCollectionMock = $this->getMock(
            '\MongoCollection',
            array('setWriteConcern'),
            array($this->database->getMongoDB(), 'test')
        );

        $mongoCollectionMock
            ->expects($this->once())
            ->method('setWriteConcern')
            ->will($this->returnValue(false));

        $collection = new Collection($this->database, $mongoCollectionMock);

        $collection->setWriteConcern(1);
    }

    public function testSetUnacknowledgedWriteConcern()
    {
        $this->collection->setUnacknowledgedWriteConcern(11000);

        $this->assertEquals(array(
            'w' => 0,
            'wtimeout' => 11000
        ), $this->collection->getWriteConcern());
    }

    public function testSetMajorityWriteConcern()
    {
        $this->collection->setMajorityWriteConcern(13000);

        $this->assertEquals(array(
            'w' => 'majority',
            'wtimeout' => 13000
        ), $this->collection->getWriteConcern());
    }

    public function testEnsureIndex()
    {
        $this->collection->ensureIndex(array(
            'asc'    => 1,
            'desc'   => -1,
        ));

        $indexes = $this->collection->getIndexes();

        $this->assertEquals(array(
            'asc'     => 1,
            'desc'    => -1,
        ), $indexes[1]['key']);

    }

    public function testEnsureSparseIndex()
    {
        $this->collection->ensureSparseIndex(array(
            'sparseAsc'     => 1,
            'sparseDesc'    => -1,
        ));

        $indexes = $this->collection->getIndexes();

        $this->assertEquals(array(
            'sparseAsc'     => 1,
            'sparseDesc'    => -1,
        ), $indexes[1]['key']);

        $this->assertArrayHasKey('sparse', $indexes[1]);

    }

    public function testEnsureTTLIndex()
    {
        $this->collection->ensureTTLIndex(array(
            'ttlAsc'    => 1,
            'ttlDesc'   => -1,
        ), 12000);

        $indexes = $this->collection->getIndexes();

        $this->assertEquals(array(
            'ttlAsc'     => 1,
            'ttlDesc'    => -1,
        ), $indexes[1]['key']);

        $this->assertArrayHasKey('expireAfterSeconds', $indexes[1]);
        $this->assertEquals(12000, $indexes[1]['expireAfterSeconds']);

    }

    public function testEnsureUniqueIndex()
    {
        $this->collection->ensureUniqueIndex(array(
            'uniqueAsc'     => 1,
            'uniqueDesc'    => -1,
        ), true);

        $indexes = $this->collection->getIndexes();

        $this->assertEquals(array(
            'uniqueAsc'     => 1,
            'uniqueDesc'    => -1,
        ), $indexes[1]['key']);

        $this->assertArrayHasKey('dropDups', $indexes[1]);
        $this->assertEquals(1, $indexes[1]['dropDups']);
    }

    public function testInitIndexes()
    {
        $reflection = new \ReflectionClass($this->collection);
        $property = $reflection->getProperty('_index');
        $property->setAccessible(true);

        $property->setValue($this->collection, array(
            array(
                'keys' => array('asc' => 1, 'desc' => -1),
                'unique' => true,
            ),
        ));

        $this->collection->initIndexes();

        $indexes = $this->collection->getIndexes();

        $this->assertEquals(array(
            'asc'     => 1,
            'desc'    => -1,
        ), $indexes[1]['key']);

        $this->assertArrayHasKey('unique', $indexes[1]);
    }

    /**
     * @expectedException \Sokil\Mongo\Exception
     * @expectedExceptionMessage Keys not specified
     */
    public function testInitIndexes_KeysNotSpecified()
    {
        $reflection = new \ReflectionClass($this->collection);
        $property = $reflection->getProperty('_index');
        $property->setAccessible(true);

        $property->setValue($this->collection, array(
            array(
                'unique' => true,
            ),
        ));

        $this->collection->initIndexes();

        $indexes = $this->collection->getIndexes();

        $this->assertEquals(array(
            'asc'     => 1,
            'desc'    => -1,
        ), $indexes[1]['key']);

        $this->assertArrayHasKey('unique', $indexes[1]);
    }
}
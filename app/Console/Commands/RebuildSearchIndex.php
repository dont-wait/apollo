<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Meilisearch\Client;
use Meilisearch\Exceptions\ApiException;

#[Signature('search:rebuild')]
#[Description('Synchronize Meilisearch settings and rebuild the post index')]
class RebuildSearchIndex extends Command
{
    public function handle(Client $client): int
    {
        $this->ensureIndexExists($client);

        if ($this->call('scout:sync-index-settings') !== self::SUCCESS) {
            return self::FAILURE;
        }

        return $this->call('scout:import', [
            'model' => Post::class,
            '--fresh' => true,
        ]);
    }

    private function ensureIndexExists(Client $client): void
    {
        $indexName = (new Post)->searchableAs();

        try {
            $client->getIndex($indexName);
        } catch (ApiException $exception) {
            if ($exception->httpStatus !== 404) {
                throw $exception;
            }

            $task = $client->createIndex($indexName, ['primaryKey' => 'id']);
            $client->waitForTask($task['taskUid']);
        }
    }
}

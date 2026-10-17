<?php
declare(strict_types=1);
namespace App\Infrastructure\Search;

interface WebPageFetcherInterface
{
    /** @return array{url:string,text:string,truncated:bool} */
    public function fetch(string $url): array;
}

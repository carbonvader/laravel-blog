<?php

namespace BinshopsBlog\Laravel\Fulltext;


interface SearchInterface
{
    public function run($search,$request);

    public function runForClass($search, $class);

    public function searchQuery($search);
}

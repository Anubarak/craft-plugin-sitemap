<?php

namespace Anubarak\Sitemap\Data;

use Anubarak\Sitemap\Models\SitemapEntry;

/**
 *
 */
class SitemapIndex
{
    /**
     * @param string   $name
     * @param int[]    $sectionIds
     * @param int|null $limit
     * @param int|null $offset
     */
    public function __construct(
        protected SitemapEntry $sitemapEntry,
        protected string       $name,
        protected array        $sectionIds,
        protected int|null     $offset = null,
        protected int|null     $limit = null,
    ) {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name . (($this->offset) ? '_' . $this->offset : '');
    }

    /**
     * @return array
     */
    public function getSectionIds(): array
    {
        return $this->sectionIds;
    }

    /**
     * @return ?int
     */
    public function getLimit(): ?int
    {
        return $this->limit;
    }

    /**
     * @return ?int
     */
    public function getOffset(): ?int
    {
        return $this->offset;
    }

    /**
     * @param int $id
     *
     * @return void
     */
    public function addSectionId(int $id): void
    {
        $this->sectionIds[] = $id;
    }

    /**
     * @return \Anubarak\Sitemap\Models\SitemapEntry
     */
    public function getSitemapEntry(): SitemapEntry
    {
        return $this->sitemapEntry;
    }
}
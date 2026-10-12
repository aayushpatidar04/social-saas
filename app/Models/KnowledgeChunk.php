<?php

// app/Models/KnowledgeChunk.php
namespace App\Models;
 
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
 
class KnowledgeChunk extends Model
{
    use BelongsToOrganization;
    protected $fillable = [
        'organization_id',
        'knowledge_source_id', 'chunk_number', 'content',
        'token_count', 'embedding', 'embedding_model'
    ];
 
    protected $casts = [
        'embedding' => 'array',
    ];
 
    public function knowledgeSource()
    {
        return $this->belongsTo(KnowledgeSource::class);
    }

    public function source()
    {
        return $this->belongsTo(KnowledgeSource::class, 'knowledge_source_id');
    }
}
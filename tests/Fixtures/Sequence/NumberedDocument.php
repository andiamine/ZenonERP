<?php

namespace Tests\Fixtures\Sequence;

use Illuminate\Database\Eloquent\Model;
use Modules\Sequence\Contracts\HasSequence;

/**
 * Fixture consumer of the HasSequence trait — a stand-in for the business documents
 * (sales orders, invoices) that will opt into numbering in later milestones. Exists so
 * the trait has a real using-class (exercised by SequenceHasSequenceTest, and analysed by
 * Larastan, which cannot check a trait that is used zero times).
 *
 * @property string|null $number
 * @property int|null $company_id
 */
class NumberedDocument extends Model
{
    use HasSequence;

    protected $table = 'numbered_documents';

    protected $guarded = [];

    public $timestamps = false;

    public function sequenceCode(): string
    {
        return 'doc';
    }
}

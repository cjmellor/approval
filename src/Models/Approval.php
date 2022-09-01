<?php

namespace Cjmellor\Approval\Models;

use Cjmellor\Approval\Enums\ApprovalStatus;
use Cjmellor\Approval\Scopes\ApprovalStateScope;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Approval extends Model
{
  protected $guarded = [];

  protected $casts = [
    'new_data' => AsArrayObject::class,
    'original_data' => AsArrayObject::class,
    'state' => ApprovalStatus::class,
  ];

  public static function booted()
  {
    static::addGlobalScope(new ApprovalStateScope());
  }

  public function approvalable(): MorphTo
  {
    return $this->morphTo();
  }

  public function commit()
  {
    if ($this->approvalable_id) {
      return $this->approvalable_type::find($this->approvalable_id)->withoutApproval()->update($this->new_data->toArray());
    } else {
      print "I'm going to create a new $this->approvable_type";
      $model = new $this->approvalable_type;
      $model->fill($this->new_data->toArray());
      $model->save();
      return $model;
    }
  }
}

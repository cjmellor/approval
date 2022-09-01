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

  public function reject()
  {
    $this->update(['state' => ApprovalStatus::Rejected]);
  }

  public function commit()
  {
    if ($this->state != ApprovalStatus::Pending) return false;
    if ($this->approvalable_id) {
      $model = $this->approvalable_type::find($this->approvalable_id)->withoutApproval()->update($this->new_data->toArray());
      $this->update(['state' => ApprovalStatus::Approved]);
      return $model;
    } else {
      $model = new $this->approvalable_type;
      $model->fill($this->new_data->toArray());
      $model->save();
      $this->update(['state' => ApprovalStatus::Approved]);
      return $model;
    }
  }
}

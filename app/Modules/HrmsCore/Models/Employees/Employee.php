<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Employees;

use App\Models\User;
use App\Modules\HrmsCore\Database\Factories\EmployeeFactory;
use App\Modules\HrmsCore\Enums\Gender;
use App\Modules\HrmsCore\Enums\MaritalStatus;
use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Modules\HrmsCore\Models\Organization\Center;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\HrmsCore\Models\Organization\DepartmentType;
use App\Modules\HrmsCore\Models\Organization\Directorate;
use App\Modules\HrmsCore\Models\Organization\Grade;
use App\Modules\HrmsCore\Models\Appraisal\Appraisal;
use App\Modules\HrmsCore\Models\Organization\Unit;
use App\Modules\HrmsCore\Models\Promotion\StaffPromotion;
use App\Modules\HrmsCore\Models\Salary\EmployeeAllowance;
use App\Modules\HrmsCore\Models\Salary\SalaryLevel;
use App\Modules\HrmsCore\Models\Salary\SalaryStep;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

/**
 * Employee model - Core employee record.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int|null $user_id
 * @property string|null $employee_staff_id
 * @property string|null $cagd_staff_id
 * @property string|null $file_number
 * @property string|null $title
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string|null $maiden_name
 * @property Gender|null $gender
 * @property \Illuminate\Support\Carbon|null $date_of_birth
 * @property string|null $nationality
 * @property MaritalStatus|null $marital_status
 * @property string|null $email
 * @property string|null $mobile
 * @property string|null $home_phone
 * @property string|null $postal_address
 * @property string|null $residential_address
 * @property string|null $town
 * @property string|null $region
 * @property string|null $gps_postcode
 * @property bool $is_any_disability
 * @property string|null $disability_details
 * @property string|null $name_of_spouse
 * @property string|null $spouse_phone_number
 * @property bool $is_any_children
 * @property int|null $number_of_children
 * @property string|null $social_security_number
 * @property int|null $grade_id
 * @property int|null $center_id
 * @property int|null $job_title_id
 * @property int|null $salary_level_id
 * @property int|null $salary_step_id
 * @property string|null $profile_photo_path
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @use HasFactory<EmployeeFactory>
 */
final class Employee extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    use HasHrmsUuid;
    use Notifiable;
    use SoftDeletes;

    protected $table = 'hrms_employees';

    protected $connection = 'landlord';

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'is_any_disability' => false,
        'is_any_children' => false,
    ];

    protected $fillable = [
        'tenant_id',
        'user_id',
        'employee_staff_id',
        'cagd_staff_id',
        'file_number',
        'title',
        'first_name',
        'middle_name',
        'last_name',
        'maiden_name',
        'gender',
        'date_of_birth',
        'nationality',
        'marital_status',
        'email',
        'mobile',
        'home_phone',
        'postal_address',
        'residential_address',
        'town',
        'region',
        'gps_postcode',
        'is_any_disability',
        'disability_details',
        'name_of_spouse',
        'spouse_phone_number',
        'is_any_children',
        'number_of_children',
        'social_security_number',
        'grade_id',
        'center_id',
        'job_title_id',
        'salary_level_id',
        'salary_step_id',
        'profile_photo_path',
        'is_active',
    ];

    /**
     * Get the employee's display name.
     */
    public function displayName(): string
    {
        return mb_trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Get the employee's full name with title.
     */
    public function fullName(): string
    {
        $parts = array_filter([
            $this->title,
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ]);

        return mb_trim(implode(' ', $parts));
    }

    /**
     * Get the associated user account.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the employee's grade.
     *
     * @return BelongsTo<Grade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /**
     * Get the employee's center/location.
     *
     * @return BelongsTo<Center, $this>
     */
    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    /**
     * Get the employee's salary level.
     *
     * @return BelongsTo<SalaryLevel, $this>
     */
    public function salaryLevel(): BelongsTo
    {
        return $this->belongsTo(SalaryLevel::class);
    }

    /**
     * Get the employee's salary step.
     *
     * @return BelongsTo<SalaryStep, $this>
     */
    public function salaryStep(): BelongsTo
    {
        return $this->belongsTo(SalaryStep::class);
    }

    /**
     * Get the employee's allowances.
     *
     * @return HasMany<EmployeeAllowance, $this>
     */
    public function employeeAllowances(): HasMany
    {
        return $this->hasMany(EmployeeAllowance::class);
    }

    /**
     * Get the employee's currently effective allowances.
     *
     * @return HasMany<EmployeeAllowance, $this>
     */
    public function activeAllowances(): HasMany
    {
        return $this->hasMany(EmployeeAllowance::class)->currentlyEffective();
    }

    /**
     * Get the employee's appraisals.
     *
     * @return HasMany<Appraisal, $this>
     */
    public function appraisals(): HasMany
    {
        return $this->hasMany(Appraisal::class);
    }

    /**
     * Get appraisals where this employee is the supervisor.
     *
     * @return HasMany<Appraisal, $this>
     */
    public function supervisorAppraisals(): HasMany
    {
        return $this->hasMany(Appraisal::class, 'supervisor_id');
    }

    /**
     * Get appraisals where this employee is the HOD reviewer.
     *
     * @return HasMany<Appraisal, $this>
     */
    public function hodAppraisals(): HasMany
    {
        return $this->hasMany(Appraisal::class, 'hod_id');
    }

    /**
     * Get appraisals awaiting this employee's review.
     *
     * @return HasMany<Appraisal, $this>
     */
    public function pendingAppraisalReviews(): HasMany
    {
        return $this->hasMany(Appraisal::class, 'supervisor_id')
            ->orWhere('hod_id', $this->id)
            ->orWhere('hr_reviewer_id', $this->id)
            ->pending();
    }

    // ==================== Promotion Relationships ====================

    /**
     * Get the promotions for this employee.
     *
     * @return HasMany<StaffPromotion, $this>
     */
    public function promotions(): HasMany
    {
        return $this->hasMany(StaffPromotion::class);
    }

    /**
     * Get promotions where this employee is the supervisor.
     *
     * @return HasMany<StaffPromotion, $this>
     */
    public function supervisorPromotions(): HasMany
    {
        return $this->hasMany(StaffPromotion::class, 'supervisor_id');
    }

    /**
     * Get promotions where this employee is the HR approver.
     *
     * @return HasMany<StaffPromotion, $this>
     */
    public function hrPromotions(): HasMany
    {
        return $this->hasMany(StaffPromotion::class, 'hr_approver_id');
    }

    /**
     * Get the departments this employee belongs to.
     *
     * @return BelongsToMany<Department, $this>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'hrms_department_employee');
    }

    /**
     * Get the department types this employee belongs to.
     *
     * @return BelongsToMany<DepartmentType, $this>
     */
    public function departmentTypes(): BelongsToMany
    {
        return $this->belongsToMany(DepartmentType::class, 'hrms_department_type_employee');
    }

    /**
     * Get the directorates this employee belongs to.
     *
     * @return BelongsToMany<Directorate, $this>
     */
    public function directorates(): BelongsToMany
    {
        return $this->belongsToMany(Directorate::class, 'hrms_directorate_employee');
    }

    /**
     * Get the units this employee belongs to.
     *
     * @return BelongsToMany<Unit, $this>
     */
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'hrms_employee_unit');
    }

    /**
     * Get the employee's current job.
     *
     * @return HasOne<CurrentJob, $this>
     */
    public function currentJob(): HasOne
    {
        return $this->hasOne(CurrentJob::class)->where('is_current', true);
    }

    /**
     * Get the employee's job history.
     *
     * @return HasMany<CurrentJob, $this>
     */
    public function jobHistory(): HasMany
    {
        return $this->hasMany(CurrentJob::class)->orderByDesc('start_date');
    }

    /**
     * Get the employee's parent information.
     *
     * @return HasOne<EmployeeParent, $this>
     */
    public function parent(): HasOne
    {
        return $this->hasOne(EmployeeParent::class);
    }

    /**
     * Get the employee's children.
     *
     * @return HasMany<Children, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Children::class);
    }

    /**
     * Get the employee's next of kin.
     *
     * @return HasOne<NextOfKin, $this>
     */
    public function nextOfKin(): HasOne
    {
        return $this->hasOne(NextOfKin::class);
    }

    /**
     * Get the employee's bank details.
     *
     * @return HasOne<BankDetails, $this>
     */
    public function bankDetails(): HasOne
    {
        return $this->hasOne(BankDetails::class);
    }

    /**
     * Get the employee's emergency contacts.
     *
     * @return HasMany<EmergencyContact, $this>
     */
    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class);
    }

    /**
     * Get the employee's educational background.
     *
     * @return HasMany<EducationalBackground, $this>
     */
    public function educationalBackgrounds(): HasMany
    {
        return $this->hasMany(EducationalBackground::class);
    }

    /**
     * Get the employee's professional qualifications.
     *
     * @return HasMany<ProfessionalQualification, $this>
     */
    public function professionalQualifications(): HasMany
    {
        return $this->hasMany(ProfessionalQualification::class);
    }

    /**
     * Get the employee's previous work experience.
     *
     * @return HasMany<PreviousWorkExperience, $this>
     */
    public function previousWorkExperiences(): HasMany
    {
        return $this->hasMany(PreviousWorkExperience::class);
    }

    /**
     * Get the employee's employment status history.
     *
     * @return HasMany<EmploymentStatus, $this>
     */
    public function employmentStatuses(): HasMany
    {
        return $this->hasMany(EmploymentStatus::class);
    }

    /**
     * Get the employee's current employment status.
     *
     * @return HasOne<EmploymentStatus, $this>
     */
    public function currentEmploymentStatus(): HasOne
    {
        return $this->hasOne(EmploymentStatus::class)->where('is_current', true);
    }

    /**
     * Scope to only active employees.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to only employees with a specific grade.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeWithGrade($query, int $gradeId)
    {
        return $query->where('grade_id', $gradeId);
    }

    /**
     * Get the employee's base salary from their salary step.
     */
    public function getBaseSalary(): float
    {
        if ($this->salaryStep === null) {
            return 0.0;
        }

        return $this->salaryStep->calculateTotalSalary();
    }

    /**
     * Get the total monthly allowances.
     */
    public function getTotalMonthlyAllowances(): float
    {
        return $this->activeAllowances()
            ->with('allowance')
            ->get()
            ->sum(fn (EmployeeAllowance $ea): float => $ea->getEffectiveAmount());
    }

    /**
     * Get the total monthly compensation (salary + allowances).
     */
    public function getTotalCompensation(): float
    {
        return $this->getBaseSalary() + $this->getTotalMonthlyAllowances();
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory<static>
     */
    protected static function newFactory(): \Illuminate\Database\Eloquent\Factories\Factory
    {
        return EmployeeFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'is_any_disability' => 'boolean',
            'is_any_children' => 'boolean',
            'number_of_children' => 'integer',
            'is_active' => 'boolean',
            'gender' => Gender::class,
            'marital_status' => MaritalStatus::class,
        ];
    }
}

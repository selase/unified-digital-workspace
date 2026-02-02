<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Database\Factories;

use App\Modules\HrmsCore\Enums\Gender;
use App\Modules\HrmsCore\Enums\MaritalStatus;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Organization\Center;
use App\Modules\HrmsCore\Models\Organization\Grade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
final class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(Gender::cases());

        return [
            'tenant_id' => fake()->uuid(),
            'user_id' => null,
            'employee_staff_id' => 'EMP-'.fake()->unique()->numerify('######'),
            'cagd_staff_id' => fake()->optional(0.7)->numerify('CAGD-######'),
            'file_number' => fake()->optional(0.5)->numerify('FN-####'),
            'title' => fake()->randomElement(['Mr.', 'Mrs.', 'Ms.', 'Dr.', 'Prof.']),
            'first_name' => fake()->firstName($gender === Gender::Male ? 'male' : 'female'),
            'middle_name' => fake()->optional(0.6)->firstName(),
            'last_name' => fake()->lastName(),
            'maiden_name' => $gender === Gender::Female ? fake()->optional(0.3)->lastName() : null,
            'gender' => $gender,
            'date_of_birth' => fake()->dateTimeBetween('-60 years', '-20 years'),
            'nationality' => fake()->country(),
            'marital_status' => fake()->randomElement(MaritalStatus::cases()),
            'email' => fake()->unique()->safeEmail(),
            'mobile' => fake()->e164PhoneNumber(),
            'home_phone' => fake()->optional(0.3)->phoneNumber(),
            'postal_address' => fake()->optional(0.5)->address(),
            'residential_address' => fake()->address(),
            'town' => fake()->city(),
            'region' => fake()->randomElement(['Greater Accra', 'Ashanti', 'Northern', 'Western', 'Eastern', 'Volta', 'Central', 'Brong Ahafo']),
            'gps_postcode' => fake()->optional(0.4)->postcode(),
            'is_any_disability' => fake()->boolean(10),
            'disability_details' => null,
            'name_of_spouse' => null,
            'spouse_phone_number' => null,
            'is_any_children' => fake()->boolean(60),
            'number_of_children' => null,
            'social_security_number' => fake()->optional(0.8)->numerify('SSN-##########'),
            'grade_id' => null,
            'center_id' => null,
            'job_title_id' => null,
            'profile_photo_path' => null,
            'is_active' => true,
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Employee $employee): void {
            // Set disability details if has disability
            if ($employee->is_any_disability) {
                $employee->disability_details = fake()->sentence();
            }

            // Set spouse info if married
            if ($employee->marital_status === MaritalStatus::Married) {
                $employee->name_of_spouse = fake()->name();
                $employee->spouse_phone_number = fake()->e164PhoneNumber();
            }

            // Set number of children if has children and not explicitly set
            if ($employee->is_any_children && $employee->number_of_children === null) {
                $employee->number_of_children = fake()->numberBetween(1, 5);
            }
        });
    }

    /**
     * Indicate that the employee is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the employee is male.
     */
    public function male(): static
    {
        return $this->state(fn (array $attributes): array => [
            'gender' => Gender::Male,
            'first_name' => fake()->firstName('male'),
            'maiden_name' => null,
        ]);
    }

    /**
     * Indicate that the employee is female.
     */
    public function female(): static
    {
        return $this->state(fn (array $attributes): array => [
            'gender' => Gender::Female,
            'first_name' => fake()->firstName('female'),
        ]);
    }

    /**
     * Indicate that the employee is married.
     */
    public function married(): static
    {
        return $this->state(fn (array $attributes): array => [
            'marital_status' => MaritalStatus::Married,
            'name_of_spouse' => fake()->name(),
            'spouse_phone_number' => fake()->e164PhoneNumber(),
        ]);
    }

    /**
     * Indicate that the employee is single.
     */
    public function single(): static
    {
        return $this->state(fn (array $attributes): array => [
            'marital_status' => MaritalStatus::Single,
            'name_of_spouse' => null,
            'spouse_phone_number' => null,
        ]);
    }

    /**
     * Indicate that the employee has a disability.
     */
    public function withDisability(?string $details = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_any_disability' => true,
            'disability_details' => $details ?? fake()->sentence(),
        ]);
    }

    /**
     * Indicate that the employee has children.
     */
    public function withChildren(?int $count = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_any_children' => true,
            'number_of_children' => $count ?? fake()->numberBetween(1, 5),
        ]);
    }

    /**
     * Indicate that the employee has no children.
     */
    public function withoutChildren(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_any_children' => false,
            'number_of_children' => null,
        ]);
    }

    /**
     * Associate the employee with a grade.
     */
    public function withGrade(?Grade $grade = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'grade_id' => $grade->id ?? Grade::factory(),
        ]);
    }

    /**
     * Associate the employee with a center.
     */
    public function withCenter(?Center $center = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'center_id' => $center->id ?? Center::factory(),
        ]);
    }

    /**
     * Set the tenant for the employee.
     */
    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenantId,
        ]);
    }
}

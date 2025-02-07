<?php

namespace App\Http\Controllers;

use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * @group Company
 *
 * API endpoints for companies
 */
class CompanyController extends Controller
{
    /**
     * Retrieve a list of companies
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->tokenCan('companies:administer') && !$request->user()->tokenCan('companies:view')) {
            return self::sendFailure('You are unauthorised to make this request',
                401);
        }

        $companies = Company::all();
        if ($companies->isEmpty()) {
            return self::sendFailure('Unable to retrieve companies at this time, please contact your administrator',
                418);
        }

        return self::sendSuccess(CompanyResource::collection($companies),
            'Retrieved companies');
    }

    /**
     * Handles the creation of a new company in the database. 
     * Requires the user to have appropriate permissions as an administrator or a client.
     *
     * This endpoint validates the input data for the company being added, checks for uniqueness 
     * of the company name within the specified city, state, and country, and optionally stores 
     * a logo file. Upon successful validation, it creates the company record and returns a JSON response 
     * with the created company's details.
     *
     * - Ensure you are authenticated and have the required scopes (`companies:administer` or `companies:add`).
     * - Make a POST request to this endpoint with the necessary fields in the request body.
     * - If a logo file is included, ensure it is in `jpg`, `jpeg`, or `png` format.
     *
     * - **Success (201)**: Returns the newly created company details in JSON format.
     * - **Failure (401)**: Unauthorized access if the user lacks the required permissions.
     * - **Failure (422)**: Validation errors if the input data is invalid.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->tokenCan('companies:administer') && !$request->user()->tokenCan('companies:add')) {
            return self::sendFailure('You are unauthorised to make this request',
                401);
        }

        $validator = Validator::make($request->all(), [
            'city' => 'required|string|between:2,255',
            'state' => 'required|string|between:2,16',
            'country' => 'required|string|between:2,255',
            'name' => [
                'required', 'string', 'between:2,255',
                function (string $attribute, mixed $value, \Closure $fail) use (
                    $request
                ) {
                    $records = DB::table('companies')
                        ->select('*')
                        ->where('name', $request->input('name'))
                        ->where('city', $request->input('city'))
                        ->where('state', $request->input('state'))
                        ->where('country', $request->input('country'))
                        ->get();
                    if ($records->isNotEmpty()) {
                        $fail("Company name, state, and country must be a unique combination");
                    }
                }
            ],
            'logo' => 'sometimes|image|mimes:jpg,jpeg,png'
        ]);

        if ($validator->fails()) {
            $messages = $validator->errors()->messages();
            return self::sendFailure($messages, 422);
        }

        $validated = $validator->safe()->all();

        if ($request->hasFile('logo')) {
            $logo_path = $request->file('logo')->store('public');
            $validated = $validator->safe()->merge(['logo_path' => $logo_path])->all();
        }

        $company = new CompanyResource(Company::create($validated));

        return self::sendSuccess($company,
            "Company created with id: $company->id", 201);
    }

    /**
     * Retrieve a single company.
     *
     * @param  string  $id
     * @param  Request  $request
     * @return JsonResponse
     */
    public function show(string $id, Request $request): JsonResponse
    {
        if (!$request->user()->tokenCan('companies:administer') && !$request->user()->tokenCan('companies:view')) {
            return self::sendFailure('You are unauthorised to make this request',
                401);
        }

        $company = Company::query()->find($id);
        if ($company === null) {
            return self::sendFailure('Specified company not found', 404);
        }

        if ($request->user()->tokenCan('companies:view') && $request->user()->company_id != $company->id) {
            return self::sendFailure('Unauthorised, user and company ID do not match.',
                401);
        }

        return self::sendSuccess(new CompanyResource($company),
            "Retrieved company with id: $company->id");
    }

    /**
     * Update the specified company in the database.
     *
     * @param  Request  $request
     * @param  string  $id
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        if (!$request->user()->tokenCan('companies:administer') && !$request->user()->tokenCan('companies:edit')) {
            return self::sendFailure('You are unauthorised to make this request',
                401);
        }

        $company = Company::find($id);

        if ($company === null) {
            return self::sendFailure("Company with id: $id not found", 404);
        }

        if ($request->user()->tokenCan('companies:edit') && $request->user()->company_id != $company->id) {
            return self::sendFailure('Unauthorised, user and company ID do not match.',
                401);
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'required', 'string', 'between:2,255',
                function (
                    string $attribute,
                    mixed $value,
                    \Closure $fail
                ) use ($request) {
                    $records = DB::table('companies')
                        ->select('*')
                        ->where('name', $request->input('name'))
                        ->where('city', $request->input('city'))
                        ->where('state', $request->input('state'))
                        ->where('country', $request->input('country'))
                        ->get();
                    if ($records->isNotEmpty()) {
                        $fail("Company name, state, and country must be a unique combination");
                    }
                }
            ],
            'city' => 'sometimes|string|between:2,255',
            'state' => 'sometimes|string|between:2,16',
            'country' => 'sometimes|string|between:2,255',
            'logo' => 'sometimes|image|mimes:jpg,jpeg,png',
        ]);

        if ($validator->fails()) {
            $messages = $validator->errors()->messages();
            return self::sendFailure($messages, 422);
        }

        $validated = $validator->safe()->all();

        if ($request->hasFile('logo')) {
            $logo_path = $request->file('logo')->store('public');
            $validated = $validator->safe()->merge(['logo_path' => $logo_path])->all();
        }

        $company->update($validated);

        return self::sendSuccess(new CompanyResource($company),
            "Company with id: $company->id updated", 201);
    }


    /**
     * Soft delete the specified company from the database
     *
     * @urlParam id integer required The ID of the company.
     *
     * @param  string  $id
     * @param  Request  $request
     * @return JsonResponse
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        if (!$request->user()->tokenCan('companies:administer') && !$request->user()->tokenCan('companies:edit')) {
            return self::sendFailure('You are unauthorised to make this request',
                401);
        }

        $company = Company::find($id);
        if ($company === null) {
            return self::sendFailure("Specified company not found", 404);
        }
        if ($request->user()->tokenCan('companies:edit') && $request->user()->company_id != $company->id) {
            return self::sendFailure('Unauthorised, user and company ID do not match.',
                401);
        }

        $company->delete();

        return self::sendSuccess(new CompanyResource($company),
            "Company with id: $company->id deleted");
    }

    /**
     * Restore the specified soft deleted company from trash.
     *
     * @urlParam id integer required The ID of the company.
     *
     * @param  string  $id
     * @param  Request  $request
     * @return JsonResponse
     */
    public function restore(string $id, Request $request): JsonResponse
    {
        if (!$request->user()->tokenCan('companies:administer')) {
            return self::sendFailure('You are unauthorised to make this request',
                401);
        }

        Company::withTrashed()
            ->where('id', $id)
            ->restore();

        return self::sendSuccess($id, "Company with id: $id restored");
    }
}

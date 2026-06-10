<?php

namespace App\Http\Controllers;

use App\Helpers\CommonHelper;
use App\Models\Guest;
use Illuminate\Http\Request;

class GuestController extends Controller
{
    /**
     * Save extended guest profile data from the React app payload.
     */
    public function saveGuestProfile(Request $request)
    {
        try {
            $profile = $this->requestProfile($request);
            $additionalInfo = $profile['additionalInfo'] ?? [];
            $guestId = $request->input('guestId')
                ?? ($additionalInfo['guestId'] ?? null);

            if ($guestId === null || $guestId === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'guestId is required',
                ], 400);
            }

            $authGuest = $request->guest;
            if (!$authGuest instanceof Guest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Guest authentication required.',
                ], 401);
            }

            if ((string) $authGuest->guest_id !== (string) $guestId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You can only update your own profile.',
                ], 403);
            }

            $guest = $authGuest;

            $addressDetails = $profile['addressDetails'] ?? [];
            $contactInfo = $profile['contactInfo'] ?? [];
            $emergencyContact = $profile['emergencyContact'] ?? [];
            $fullNameAndGender = $profile['fullNameAndGender'] ?? [];
            $healthInfo = $profile['healthInfo'] ?? [];
            $passportDetails = $profile['passportDetails'] ?? [];
            $paymentAndDocuments = $profile['paymentAndDocuments'] ?? [];
            $preferences = $profile['preferences'] ?? [];
            $travelSlamBook = $profile['travelSlamBook'] ?? [];

            $paymentPassportDetails = $paymentAndDocuments['passportDetails'] ?? [];
            if (empty($passportDetails) && !empty($paymentPassportDetails)) {
                $passportDetails = $paymentPassportDetails;
            }

            $contactEmail = $contactInfo['email'] ?? $request->input('email');

            $guest->guest_id2 = (string) $guestId;
            $guest->languages = $additionalInfo['languages'] ?? null;
            $guest->occupation = $additionalInfo['occupation'] ?? null;

            $guest->address_line1 = $addressDetails['addressLine1'] ?? null;
            $guest->address_line2 = $addressDetails['addressLine2'] ?? null;
            $guest->city = $addressDetails['city'] ?? null;
            $guest->state_region = $addressDetails['stateRegion'] ?? null;
            $guest->postal_code = $addressDetails['postalCode'] ?? null;
            $guest->country = $addressDetails['country'] ?? null;

            $guest->phone = $contactInfo['phone'] ?? null;
            $guest->email2 = $contactEmail ?: null;
            $guest->country_of_residence = $contactInfo['countryOfResidence'] ?? null;

            $guest->name = $emergencyContact['name'] ?? null;
            $guest->relationship = $emergencyContact['relationship'] ?? null;
            $guest->phone_number = $emergencyContact['phoneNumber'] ?? null;

            $guest->title = $fullNameAndGender['title'] ?? null;
            $guest->first_name = $fullNameAndGender['firstName'] ?? null;
            $guest->middle_name = $fullNameAndGender['middleName'] ?? null;
            $guest->last_name = $fullNameAndGender['lastName'] ?? null;
            $guest->full_name = $fullNameAndGender['fullName'] ?? null;
            $guest->date_of_birth = $this->nullableDate($fullNameAndGender['dateOfBirth'] ?? null);
            $guest->gender = $fullNameAndGender['gender'] ?? null;

            $guest->allergies = $healthInfo['allergies'] ?? null;
            $guest->disabilities = $healthInfo['disabilities'] ?? null;
            $guest->blood_group = $healthInfo['bloodGroup'] ?? null;

            $guest->passport_number = $passportDetails['passportNumber'] ?? null;
            $guest->passport_nationality = $passportDetails['passportNationality'] ?? null;
            $guest->passport_issue_date = $this->nullableDate($passportDetails['passportIssueDate'] ?? null);
            $guest->passport_expiry_date = $this->nullableDate($passportDetails['passportExpiryDate'] ?? null);

            $guest->payment_passport_details = !empty($paymentPassportDetails)
                ? $paymentPassportDetails
                : null;
            $guest->government_approved_id = $paymentAndDocuments['governmentApprovedId'] ?? null;

            $guest->seat_preference = $preferences['seatPreference'] ?? null;
            $guest->room_preference = $preferences['roomPreference'] ?? null;
            $guest->meal_preference = $preferences['mealPreference'] ?? null;
            $guest->dietary_type = $preferences['dietaryType'] ?? null;
            $guest->personalization = $preferences['personalization'] ?? null;

            $guest->favorite_place = $travelSlamBook['favoritePlace'] ?? null;
            $guest->travel_bucket_list = $travelSlamBook['travelBucketList'] ?? null;
            $guest->favourite_cuisine = $travelSlamBook['favouriteCuisine'] ?? null;
            $guest->favourite_colour = $travelSlamBook['favouriteColour'] ?? null;
            $guest->dream_destination = $travelSlamBook['dreamDestination'] ?? null;
            $guest->travel_mood = $travelSlamBook['travelMood'] ?? null;
            $guest->favourite_travel_companion = $travelSlamBook['favouriteTravelCompanion'] ?? null;
            $guest->best_travel_memory = $travelSlamBook['bestTravelMemory'] ?? null;

            $guest->uploaded_images = $this->resolveUploadedImages($request, $guest, $travelSlamBook);

            if ($request->hasFile('master_image')) {
                $pathData = CommonHelper::image_path('file_storage', $request->file('master_image'));
                if (!empty($pathData['master_value'])) {
                    $guest->image = $pathData['master_value'];
                }
            }

            if (!empty($fullNameAndGender['fullName'])) {
                $guest->guest_name = $fullNameAndGender['fullName'];
            }
            if (!empty($fullNameAndGender['title'])) {
                $guest->salutation = $fullNameAndGender['title'];
            }
            if (!empty($contactEmail)) {
                $guest->email = $contactEmail;
            }
            if (!empty($contactInfo['phone'])) {
                $guest->contact = $contactInfo['phone'];
            }
            if (!empty($passportDetails['passportNumber'])) {
                $guest->passport = $passportDetails['passportNumber'];
            }
            if (!empty($passportDetails['passportExpiryDate'])) {
                $guest->passport_exp = $this->nullableDate($passportDetails['passportExpiryDate']);
            }

            $guest->save();

            return response()->json([
                'success' => true,
                'message' => 'Guest profile saved successfully',
                'data' => $guest->refresh(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving guest profile',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function requestProfile(Request $request): array
    {
        $profile = $this->decodePayload($request->input('profile'));

        if (!empty($profile)) {
            return $profile;
        }

         

        $flatProfile = [];
        foreach ($sectionKeys as $key) {
            $section = $this->decodePayload($request->input($key));
            if (!empty($section)) {
                $flatProfile[$key] = $section;
            }
        }

        return $flatProfile;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function nullableDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $travelSlamBook
     * @return array<int, string>
     */
    private function resolveUploadedImages(Request $request, Guest $guest, array $travelSlamBook): array
    {
        $existing = $guest->uploaded_images;
        if (is_string($existing)) {
            $existing = json_decode($existing, true);
        }
        if (!is_array($existing)) {
            $existing = [];
        }

        $fromPayload = $travelSlamBook['uploadedImages'] ?? [];
        if (!is_array($fromPayload)) {
            $fromPayload = [];
        }

        $keptUrls = array_values(array_filter($fromPayload, function ($item) {
            return is_string($item) && $item !== '';
        }));

        $newPaths = [];
        if ($request->hasFile('all_images')) {
            foreach ($request->file('all_images') as $image) {
                $pathData = CommonHelper::image_path('file_storage', $image);
                if (!empty($pathData['master_value'])) {
                    $newPaths[] = $pathData['master_value'];
                }
            }
        }

        return array_values(array_unique(array_merge($keptUrls, $newPaths)));
    }
}

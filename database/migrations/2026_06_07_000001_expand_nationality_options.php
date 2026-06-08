<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\ProfileField;

return new class extends Migration
{
    public function up(): void
    {
        // Bug #18: Expand nationality dropdown to a comprehensive global list
        $field = ProfileField::where('key', 'nationality')->first();
        if ($field) {
            $field->update([
                'options' => [
                    'Afghan', 'Albanian', 'Algerian', 'American', 'Andorran', 'Angolan', 'Argentine',
                    'Armenian', 'Australian', 'Austrian', 'Azerbaijani', 'Bahraini', 'Bangladeshi',
                    'Belarusian', 'Belgian', 'Bolivian', 'Bosnian', 'Brazilian', 'British', 'Bulgarian',
                    'Cameroonian', 'Canadian', 'Chilean', 'Chinese', 'Colombian', 'Congolese', 'Croatian',
                    'Cuban', 'Czech', 'Danish', 'Dominican', 'Dutch', 'Ecuadorian', 'Egyptian',
                    'Emirati', 'Estonian', 'Ethiopian', 'Filipino', 'Finnish', 'French', 'Ghanaian',
                    'Greek', 'Guatemalan', 'Honduran', 'Hungarian', 'Indian', 'Indonesian', 'Iranian',
                    'Iraqi', 'Irish', 'Israeli', 'Italian', 'Jamaican', 'Japanese', 'Jordanian',
                    'Kazakhstani', 'Kenyan', 'Korean', 'Kuwaiti', 'Kyrgyz', 'Lebanese', 'Libyan',
                    'Lithuanian', 'Luxembourgish', 'Malaysian', 'Maldivian', 'Mexican', 'Moldovan',
                    'Moroccan', 'Mozambican', 'Myanmar', 'Namibian', 'Nepalese', 'New Zealander',
                    'Nicaraguan', 'Nigerian', 'Norwegian', 'Omani', 'Pakistani', 'Palestinian',
                    'Paraguayan', 'Peruvian', 'Polish', 'Portuguese', 'Qatari', 'Romanian', 'Russian',
                    'Rwandan', 'Saudi Arabian', 'Senegalese', 'Serbian', 'Singaporean', 'Slovak',
                    'Slovenian', 'Somali', 'South African', 'Spanish', 'Sri Lankan', 'Sudanese',
                    'Swedish', 'Swiss', 'Syrian', 'Taiwanese', 'Tajik', 'Tanzanian', 'Thai',
                    'Tunisian', 'Turkish', 'Ugandan', 'Ukrainian', 'Uruguayan', 'Uzbek',
                    'Venezuelan', 'Vietnamese', 'Yemeni', 'Zambian', 'Zimbabwean', 'Other',
                ]
            ]);
        }
    }

    public function down(): void
    {
        $field = ProfileField::where('key', 'nationality')->first();
        if ($field) {
            $field->update([
                'options' => ['British', 'Pakistani', 'American', 'Indian', 'Other']
            ]);
        }
    }
};

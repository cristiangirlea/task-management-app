<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\App;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class LanguageTranslationsTest extends TestCase
{
    // List of languages to test
    protected array $languages = ['en', 'fr'];

    // List of translation files and their keys to validate
    protected array $translationFiles = [
        'auth' => [
            'login.success',
            'login.error',
            'logout.success',
        ],
        'tenant' => [
            'store.success',
            'store.error',
            'update.success',
            'update.error',
            'destroy.success',
            'destroy.error',
        ],
        'messages' => [
            'store.success',
            'store.error',
            'update.success',
            'update.error',
            'destroy.success',
            'destroy.error',
            'show.success',
            'show.not_found_error',
        ],
    ];

    #[Test]
    public function itChecksAllTranslationKeysExistForEveryLanguage()
    {
        foreach ($this->languages as $language) {
            App::setLocale($language); // Set applicable locale

            foreach ($this->translationFiles as $file => $keys) {
                foreach ($keys as $key) {
                    $fullKey = "{$file}.{$key}";

                    // Assert the translation key exists
                    $this->assertTrue(
                        trans()->has($fullKey),
                        "Missing key: '{$fullKey}' in {$language} language file"
                    );

                    // Assert the translation value is not null
                    $translationValue = trans($fullKey);
                    $this->assertNotNull($translationValue, "Null value for key: '{$fullKey}' in {$language} language file");

                    // Assert the translation value is a string (or array if lists are used)
                    $this->assertTrue(
                        is_string($translationValue) || is_array($translationValue),
                        "The value for key '{$fullKey}' in {$language} is not a string or array"
                    );
                }
            }
        }
    }

    #[Test]
    public function itChecksSpecificTranslationValuesForEnglish()
    {
        App::setLocale('en'); // Use English locale

        // Assert specific keys return expected values
        $this->assertEquals(
            'Logged in successfully.',
            trans('auth.login.success')
        );

        $this->assertEquals(
            'Tenant has been successfully created.',
            trans('tenant.store.success')
        );
    }

    #[Test]
    public function itChecksSpecificTranslationValuesForFrench()
    {
        App::setLocale('fr'); // Use French locale

        // Assert specific keys return expected values
        $this->assertEquals(
            'Connecté avec succès.',
            trans('auth.login.success')
        );

        $this->assertEquals(
            'Le locataire a été créé avec succès.',
            trans('tenant.store.success')
        );
    }
}

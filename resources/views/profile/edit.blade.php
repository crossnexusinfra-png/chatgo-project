@extends('layouts.app')

@php
    // コントローラーから渡された$langを使用、なければ取得
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp

@section('title')
    {{ \App\Services\LanguageService::trans('profile_edit', $lang ?? \App\Services\LanguageService::getCurrentLanguage()) }}
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/profile.css') }}">
@endpush

@section('content')
<div class="profile-edit-container">
    <div class="profile-edit-header">
        <a href="{{ route('profile.index') }}" class="back-btn">← {{ \App\Services\LanguageService::trans('back_to_profile', $lang) }}</a>
        <h1>{{ \App\Services\LanguageService::trans('profile_edit', $lang) }}</h1>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="profile-edit-content">
        <form action="{{ route('profile.update') }}" method="POST" class="profile-form">
            @csrf
            @method('PUT')

            <div class="form-section">
                <h2>{{ \App\Services\LanguageService::trans('basic_info', $lang) }}</h2>
                
                <div class="form-group">
                    <label for="username">{{ \App\Services\LanguageService::trans('username', $lang) }} <span class="required">*</span></label>
                    <input type="text" id="username" name="username" value="{{ old('username', $user->username) }}" readonly class="readonly-field">
                    <p class="help-text">{{ \App\Services\LanguageService::trans('cannot_change_username', $lang) }}</p>
                </div>
                
                <div class="form-group">
                    <label for="user_identifier">{{ \App\Services\LanguageService::trans('user_identifier', $lang) }} <span class="required">*</span></label>
                    <input type="text" id="user_identifier" name="user_identifier" value="{{ old('user_identifier', $user->user_identifier) }}" readonly class="readonly-field">
                    <p class="help-text">{{ \App\Services\LanguageService::trans('cannot_change_user_identifier', $lang) }}</p>
                </div>

                <div class="form-group">
                    <label for="email">{{ \App\Services\LanguageService::trans('email', $lang) }} <span class="required">*</span></label>
                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required>
                </div>

                <div class="form-group">
                    <label for="phone">{{ \App\Services\LanguageService::trans('phone', $lang) }} <span class="required">*</span></label>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" required>
                    @error('phone')
                        <span class="error-message error-message-inline">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="residence">{{ \App\Services\LanguageService::trans('residence', $lang) }} <span class="required">*</span></label>
                    <select id="residence" name="residence" required>
                        <option value="">{{ \App\Services\LanguageService::trans('select_country', $lang) }}</option>
                        <option value="US" {{ old('residence', $user->residence) == 'US' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_usa', $lang) }}</option>
                        <option value="CA" {{ old('residence', $user->residence) == 'CA' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_canada', $lang) }}</option>
                        <option value="GB" {{ old('residence', $user->residence) == 'GB' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_uk', $lang) }}</option>
                        <option value="DE" {{ old('residence', $user->residence) == 'DE' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_de', $lang) }}</option>
                        <option value="FR" {{ old('residence', $user->residence) == 'FR' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_fr', $lang) }}</option>
                        <option value="NL" {{ old('residence', $user->residence) == 'NL' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_nl', $lang) }}</option>
                        <option value="BE" {{ old('residence', $user->residence) == 'BE' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_be', $lang) }}</option>
                        <option value="SE" {{ old('residence', $user->residence) == 'SE' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_se', $lang) }}</option>
                        <option value="FI" {{ old('residence', $user->residence) == 'FI' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_fi', $lang) }}</option>
                        <option value="DK" {{ old('residence', $user->residence) == 'DK' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_dk', $lang) }}</option>
                        <option value="NO" {{ old('residence', $user->residence) == 'NO' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_no', $lang) }}</option>
                        <option value="IS" {{ old('residence', $user->residence) == 'IS' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_is', $lang) }}</option>
                        <option value="AT" {{ old('residence', $user->residence) == 'AT' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_at', $lang) }}</option>
                        <option value="CH" {{ old('residence', $user->residence) == 'CH' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_ch', $lang) }}</option>
                        <option value="IE" {{ old('residence', $user->residence) == 'IE' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_ie', $lang) }}</option>
                        <option value="JP" {{ old('residence', $user->residence) == 'JP' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_japan', $lang) }}</option>
                        <option value="KR" {{ old('residence', $user->residence) == 'KR' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_kr', $lang) }}</option>
                        <option value="SG" {{ old('residence', $user->residence) == 'SG' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_sg', $lang) }}</option>
                        <option value="AU" {{ old('residence', $user->residence) == 'AU' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_australia', $lang) }}</option>
                        <option value="NZ" {{ old('residence', $user->residence) == 'NZ' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_nz', $lang) }}</option>
                        <option value="OTHER" {{ old('residence', $user->residence) == 'OTHER' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_other', $lang) }}</option>
                    </select>
                </div>
            </div>

            <div class="form-section">
                <h2>{{ \App\Services\LanguageService::trans('profile_image', $lang) }}</h2>
                
                <div class="form-group">
                    <label>{{ \App\Services\LanguageService::trans('profile_image', $lang) }}</label>
                    
                        @if($user->profile_image)
                            <div class="current-image">
                                @php
                                    // アバター画像（public/images/avatars/）の場合はasset()を使用
                                    // それ以外（storage/）の場合はStorage::url()を使用（S3対応）
                                    if (strpos($user->profile_image, 'avatars/') !== false || strpos($user->profile_image, 'images/avatars/') !== false) {
                                        $imageUrl = asset($user->profile_image);
                                    } else {
                                        $imageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($user->profile_image);
                                    }
                                @endphp
                                <img src="{{ $imageUrl }}" alt="{{ \App\Services\LanguageService::trans('profile_image', $lang) }}" class="preview-image">
                                <p class="current-image-text">{{ \App\Services\LanguageService::trans('current_image', $lang) }}</p>
                            </div>
                        @endif
                    
                    <div class="image-selection-section">
                        <p class="help-text">{{ \App\Services\LanguageService::trans('select_default_image_help', $lang) }}</p>
                        @php
                            // 利用可能なアバター画像のリストをカテゴリー別に分類
                            $manAvatars = [
                                'man01', 'man02', 'man03', 'man04', 'man05', 'man06', 'man07', 'man08', 'man09', 'man10',
                                'man11', 'man12', 'man13', 'man14', 'man15', 'man16', 'man17', 'man18', 'man19', 'man20',
                                'man21', 'man22', 'man23', 'man24', 'man25', 'man26', 'man27', 'man28', 'man29', 'man30',
                                'man31', 'man32', 'man33', 'man34', 'man35', 'man36', 'man37', 'man38',
                            ];
                            $womanAvatars = [
                                'woman01', 'woman02', 'woman03', 'woman04', 'woman05', 'woman06', 'woman07', 'woman08', 'woman09', 'woman10',
                                'woman11', 'woman12', 'woman13', 'woman14', 'woman15', 'woman16', 'woman17', 'woman18', 'woman19', 'woman20',
                                'woman21', 'woman22', 'woman23', 'woman24', 'woman25', 'woman26', 'woman27', 'woman28', 'woman29', 'woman30',
                                'woman31', 'woman32', 'woman33', 'woman34', 'woman35', 'woman36', 'woman37', 'woman38',
                            ];
                            $animalAvatars = [
                                'animal01', 'animal02', 'animal03', 'animal04', 'animal05', 'animal06', 'animal07', 'animal08', 'animal09', 'animal10',
                                'animal11', 'animal12', 'animal13', 'animal14', 'animal15', 'animal16', 'animal17', 'animal18', 'animal19', 'animal20',
                                'animal21', 'animal22', 'animal23', 'animal24', 'animal25', 'animal26', 'animal27', 'animal28',
                            ];
                            
                            $currentAvatar = '';
                            if ($user->profile_image && (strpos($user->profile_image, 'avatars/') !== false || strpos($user->profile_image, 'images/avatars/') !== false)) {
                                $currentAvatar = basename($user->profile_image);
                            }
                            $oldDefaultAvatar = old('default_avatar');
                            if ($oldDefaultAvatar !== null) {
                                $currentAvatar = $oldDefaultAvatar === 'none' ? '' : $oldDefaultAvatar;
                            }
                            $isNoneSelected = empty($currentAvatar) && !$user->profile_image;
                            
                            // グローバルインデックス（全アバターを通しての連番）
                            $globalIndex = 0;
                        @endphp
                        
                        @php
                            // 現在選択されているアバターのカテゴリーを判定
                            $selectedCategory = '';
                            if ($currentAvatar) {
                                if (strpos($currentAvatar, 'man') === 0) {
                                    $selectedCategory = 'man';
                                } elseif (strpos($currentAvatar, 'woman') === 0) {
                                    $selectedCategory = 'woman';
                                } elseif (strpos($currentAvatar, 'animal') === 0) {
                                    $selectedCategory = 'animal';
                                }
                            }
                            if ($isNoneSelected || $oldDefaultAvatar === 'none') {
                                $selectedCategory = 'none';
                            }
                        @endphp
                        
                        {{-- 非選択オプション --}}
                        <div class="avatar-category-section">
                            <h3 class="avatar-category-title accordion-header" data-category="none">
                                <span class="category-name">{{ \App\Services\LanguageService::trans('avatar_category_none', $lang) }}</span>
                                <span class="accordion-icon">▼</span>
                            </h3>
                            <div class="avatar-grid accordion-content {{ $selectedCategory === 'none' ? 'open' : '' }}" data-category="none">
                                <label class="avatar-option none-option {{ ($isNoneSelected || $oldDefaultAvatar === 'none') ? 'selected' : '' }}">
                                    <input type="radio" name="default_avatar" value="none" {{ ($isNoneSelected || $oldDefaultAvatar === 'none') ? 'checked' : '' }}>
                                    <div class="avatar-placeholder-option">
                                        <span class="avatar-icon-large">👤</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        {{-- 男性アバター --}}
                        <div class="avatar-category-section">
                            <h3 class="avatar-category-title accordion-header" data-category="man">
                                <span class="category-name">{{ \App\Services\LanguageService::trans('avatar_category_man', $lang) }}</span>
                                <span class="accordion-icon">▼</span>
                            </h3>
                            <div class="avatar-grid accordion-content {{ $selectedCategory === 'man' ? 'open' : '' }}" data-category="man">
                                @foreach($manAvatars as $avatarName)
                                    @php
                                        $globalIndex++;
                                        $avatarPath = "images/avatars/{$avatarName}.png";
                                        $avatarFileName = "{$avatarName}.png";
                                        $isSelected = $currentAvatar === $avatarFileName;
                                    @endphp
                                    <label class="avatar-option {{ $isSelected ? 'selected' : '' }}">
                                        <input type="radio" name="default_avatar" value="{{ $avatarFileName }}" {{ $isSelected ? 'checked' : '' }}>
                                        <img src="{{ asset($avatarPath) }}" alt="{{ $avatarName }}" class="avatar-thumbnail avatar-thumbnail-hidden" data-index="{{ $globalIndex }}">
                                        <div class="avatar-placeholder" id="avatar-placeholder-{{ $globalIndex }}">{{ $avatarName }}</div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        
                        {{-- 女性アバター --}}
                        <div class="avatar-category-section">
                            <h3 class="avatar-category-title accordion-header" data-category="woman">
                                <span class="category-name">{{ \App\Services\LanguageService::trans('avatar_category_woman', $lang) }}</span>
                                <span class="accordion-icon">▼</span>
                            </h3>
                            <div class="avatar-grid accordion-content {{ $selectedCategory === 'woman' ? 'open' : '' }}" data-category="woman">
                                @foreach($womanAvatars as $avatarName)
                                    @php
                                        $globalIndex++;
                                        $avatarPath = "images/avatars/{$avatarName}.png";
                                        $avatarFileName = "{$avatarName}.png";
                                        $isSelected = $currentAvatar === $avatarFileName;
                                    @endphp
                                    <label class="avatar-option {{ $isSelected ? 'selected' : '' }}">
                                        <input type="radio" name="default_avatar" value="{{ $avatarFileName }}" {{ $isSelected ? 'checked' : '' }}>
                                        <img src="{{ asset($avatarPath) }}" alt="{{ $avatarName }}" class="avatar-thumbnail avatar-thumbnail-hidden" data-index="{{ $globalIndex }}">
                                        <div class="avatar-placeholder" id="avatar-placeholder-{{ $globalIndex }}">{{ $avatarName }}</div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        
                        {{-- 動物アバター --}}
                        <div class="avatar-category-section">
                            <h3 class="avatar-category-title accordion-header" data-category="animal">
                                <span class="category-name">{{ \App\Services\LanguageService::trans('avatar_category_animal', $lang) }}</span>
                                <span class="accordion-icon">▼</span>
                            </h3>
                            <div class="avatar-grid accordion-content {{ $selectedCategory === 'animal' ? 'open' : '' }}" data-category="animal">
                                @foreach($animalAvatars as $avatarName)
                                    @php
                                        $globalIndex++;
                                        $avatarPath = "images/avatars/{$avatarName}.png";
                                        $avatarFileName = "{$avatarName}.png";
                                        $isSelected = $currentAvatar === $avatarFileName;
                                    @endphp
                                    <label class="avatar-option {{ $isSelected ? 'selected' : '' }}">
                                        <input type="radio" name="default_avatar" value="{{ $avatarFileName }}" {{ $isSelected ? 'checked' : '' }}>
                                        <img src="{{ asset($avatarPath) }}" alt="{{ $avatarName }}" class="avatar-thumbnail avatar-thumbnail-hidden" data-index="{{ $globalIndex }}">
                                        <div class="avatar-placeholder" id="avatar-placeholder-{{ $globalIndex }}">{{ $avatarName }}</div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2>{{ \App\Services\LanguageService::trans('bio', $lang) }}</h2>
                
                <div class="form-group">
                    <label for="bio">{{ \App\Services\LanguageService::trans('bio', $lang) }}</label>
                    <textarea id="bio" name="bio" rows="5" placeholder="{{ \App\Services\LanguageService::trans('bio_placeholder', $lang) }}">{{ old('bio', $user->bio) }}</textarea>
                    <div class="char-count">
                        <span id="charCount">0</span>/100{{ \App\Services\LanguageService::trans('characters', $lang) }}
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2>{{ \App\Services\LanguageService::trans('language_setting', $lang) }}</h2>
                
                <div class="form-group">
                    <label for="language">{{ \App\Services\LanguageService::trans('language', $lang) }} <span class="required">*</span></label>
                    <select id="language" name="language" required>
                        @php
                            $currentLanguage = old('language', $user->settings['language'] ?? 'JA');
                            // 既存データとの互換性：小文字の場合は大文字に変換
                            if ($currentLanguage === 'ja') $currentLanguage = 'JA';
                            if ($currentLanguage === 'en') $currentLanguage = 'EN';
                        @endphp
                        <option value="JA" {{ $currentLanguage == 'JA' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('language_ja', $lang) }}</option>
                        <option value="EN" {{ $currentLanguage == 'EN' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('language_en', $lang) }}</option>
                    </select>
                    <p class="help-text">{{ \App\Services\LanguageService::trans('language_help', $lang) }}</p>
                </div>
            </div>

            <div class="form-section">
                <h2>{{ \App\Services\LanguageService::trans('unchangeable_items', $lang) }}</h2>
                
                <div class="form-group">
                    <label>{{ \App\Services\LanguageService::trans('nationality', $lang) }}</label>
                    <input type="text" value="{{ $user->nationality }}" readonly class="readonly-field">
                    <p class="help-text">{{ \App\Services\LanguageService::trans('cannot_change_nationality', $lang) }}</p>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">{{ \App\Services\LanguageService::trans('save', $lang) }}</button>
                <a href="{{ route('profile.index') }}" class="btn btn-secondary">{{ \App\Services\LanguageService::trans('cancel', $lang) }}</a>
            </div>
        </form>
    </div>
</div>

    <script src="{{ asset('js/profile-edit.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
@endsection


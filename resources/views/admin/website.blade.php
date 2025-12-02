@extends('layouts.admin')

@section('content')
    <div class="row mb-3">
        <div class="col-sm-6">
            <h1 class="m-0 text-dark">Website Setup</h1>
            <small class="text-muted">Manage branding, contact information, and meta details.</small>
        </div>
        <div class="col-sm-6 text-right">
            <button form="website-setup-form" type="submit" class="btn btn-primary">Save changes</button>
        </div>
    </div>

    <form id="website-setup-form" method="post" action="{{ route('admin.settings.website.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="row">
            <div class="col-lg-6">
                <div class="card card-outline card-primary">
                    <div class="card-header"><h3 class="card-title">Brand Assets</h3></div>
                    <div class="card-body">
                        @php
                            $assets = [
                                ['field' => 'logo', 'label' => 'Logo'],
                                ['field' => 'favicon', 'label' => 'Favicon'],
                                ['field' => 'ogImage', 'label' => 'OG Image'],
                            ];
                        @endphp
                        @foreach($assets as $asset)
                            <div class="form-group">
                                <label>{{ $asset['label'] }}</label>
                                <div class="custom-file mb-2">
                                    <input type="file" name="{{ $asset['field'] }}" class="custom-file-input" id="{{ $asset['field'] }}Input">
                                    <label class="custom-file-label" for="{{ $asset['field'] }}Input">Choose file</label>
                                </div>
                                @if(!empty($settings[$asset['field']]))
                                    <div class="mb-2">
                                        <img src="{{ $settings[$asset['field']] }}" alt="{{ $asset['label'] }}" style="max-height:80px;">
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="remove_{{ $asset['field'] }}" value="1" id="remove_{{ $asset['field'] }}">
                                        <label class="form-check-label" for="remove_{{ $asset['field'] }}">Remove {{ strtolower($asset['label']) }}</label>
                                    </div>
                                @else
                                    <p class="text-muted">No {{ strtolower($asset['label']) }} uploaded.</p>
                                @endif
                            </div>
                            <hr>
                        @endforeach
                    </div>
                </div>

                <div class="card card-outline card-secondary">
                    <div class="card-header"><h3 class="card-title">Contact Information</h3></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Contact Email</label>
                            <input name="contactEmail" type="email" class="form-control" value="{{ $settings['contactEmail'] ?? '' }}" placeholder="hello@oworld.com">
                        </div>
                        <div class="form-group">
                            <label>Contact Phone</label>
                            <input name="contactPhone" type="text" class="form-control" value="{{ $settings['contactPhone'] ?? '' }}" placeholder="+1 555 123 4567">
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="address" rows="2" class="form-control" placeholder="123 Main Street, City">{{ $settings['address'] ?? '' }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card card-outline card-info">
                    <div class="card-header"><h3 class="card-title">Basic Information</h3></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Site Title</label>
                            <input name="siteTitle" type="text" class="form-control" value="{{ $settings['siteTitle'] ?? '' }}" required>
                        </div>
                        <div class="form-group">
                            <label>Tagline</label>
                            <input name="tagline" type="text" class="form-control" value="{{ $settings['tagline'] ?? '' }}">
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" rows="3" class="form-control" placeholder="Brief description used around the site.">{{ $settings['description'] ?? '' }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="card card-outline card-warning">
                    <div class="card-header"><h3 class="card-title">Meta & SEO</h3></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Meta Keywords</label>
                            <textarea name="metaKeywords" rows="2" class="form-control" placeholder="events, offers, city experiences">{{ $settings['metaKeywords'] ?? '' }}</textarea>
                        </div>
                        <div class="form-group">
                            <label>Meta Description</label>
                            <textarea name="metaDescription" rows="3" class="form-control" placeholder="Short description used for SEO and social sharing.">{{ $settings['metaDescription'] ?? '' }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Save Website Settings</h5>
                            <small class="text-muted">Stores values in site_general setting.</small>
                        </div>
                        <button class="btn btn-primary" type="submit">Save</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

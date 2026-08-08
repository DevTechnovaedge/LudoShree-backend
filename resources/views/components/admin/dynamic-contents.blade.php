@if(old('dynamic_content'))
    @foreach(old('dynamic_content') as $dynamic_content)
        <!-- Templet : Dynamic Content Card -->
        <div class="card p-4 dynamic-content clone-template" data-clone-template-id="{{ $loop->iteration }}">
            <div class="row item-row">
                <!-- Heading -->
                <div class="col-md-12">
                    <div class="form-group">
                        <label for="heading">Heading</label>
                        <input type="text" class="form-control" placeholder="Enter heading" name="dynamic_content[{{ $loop->iteration }}][heading]" id="heading" value="{{ $dynamic_content['heading'] }}" >
                        @error("dynamic_content.$loop->iteration.heading") <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
                <!-- End Heading -->

                <!-- Description -->
                <div class="col-md-12">
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control ckeditor" placeholder="Enter description" name="dynamic_content[{{ $loop->iteration }}][description]" id="description" >{{ $dynamic_content['description'] }}</textarea>
                        @error("dynamic_content.$loop->iteration.description") <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
                <!-- End Description -->
            </div>
        </div>
        <!-- End Templet : Dynamic Content Card -->
    @endforeach
@else
    @forelse($dynamicContents ?? [] as $row)
        <!-- Templet : Dynamic Content Card -->
        <div class="card p-4 dynamic-content clone-template" data-clone-template-id="{{ $loop->iteration }}">
            @if(!$loop->first)
                <span class="remove-clone-template-row fa fa-trash" onclick="removeCloneTemplateRow(this)"></span>
            @endif

            <div class="row item-row">
                <!-- Heading -->
                <div class="col-md-12">
                    <div class="form-group">
                        <label for="heading">Heading</label>
                        <input type="text" class="form-control" placeholder="Enter heading" name="dynamic_content[{{ $loop->iteration }}][heading]" id="heading" value="{{ $row->heading ?? '' }}" >
                        @error("dynamic_content.$loop->iteration.heading") <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
                <!-- End Heading -->

                <!-- Description -->
                <div class="col-md-12">
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control ckeditor" placeholder="Enter description" name="dynamic_content[{{ $loop->iteration }}][description]" id="description" > {{ $row->description ?? '' }}</textarea>
                        @error("dynamic_content.$loop->iteration.description") <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
                <!-- End Description -->
            </div>
        </div>
        <!-- End Templet : Dynamic Content Card -->
    @empty
        <!-- Templet : Dynamic Content Card -->
        <div class="card p-4 dynamic-content clone-template" data-clone-template-id="1">
            <div class="row item-row">
                <!-- Heading -->
                <div class="col-md-12">
                    <div class="form-group">
                        <label for="heading">Heading</label>
                        <input type="text" class="form-control" placeholder="Enter heading" name="dynamic_content[1][heading]" id="heading" >
                        @error("dynamic_content.1.heading") <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
                <!-- End Heading -->

                <!-- Description -->
                <div class="col-md-12">
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control ckeditor" placeholder="Enter description" name="dynamic_content[1][description]" id="description" ></textarea>
                        @error("dynamic_content.1.description") <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
                <!-- End Description -->
            </div>
        </div>
        <!-- End Templet : Dynamic Content Card -->
    @endforelse
@endif
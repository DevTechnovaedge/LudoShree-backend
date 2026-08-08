
@forelse( old('video_gallery') ?? $items ?? [] as $item)
 {{-- @dd(old('video_gallery')) --}}
        <!-- Static Video Gallery Card -->
        <div class="card p-4  video-gallery clone-template" data-clone-template-id="{{ $loop->iteration }}">
        
            @if(!$loop->first)
                <span class="remove-clone-template-row fa fa-trash" onclick="removeCloneTemplateRow(this)"></span>
            @endif

            <div class="row item-row">

               {{-- new --}}
               <div class="col-md-12">
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" class="form-control" placeholder="Enter video title" name="video_gallery[{{ $loop->iteration }}][title]" id="title" value="{{ is_object($item) ? ($item->title ?? '') : (is_array($item) ? ($item['title'] ?? '') : '') }}">
                        @error("video_gallery.$loop->iteration.title") <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
               {{-- new end --}}



                <!-- Youtube Link -->
                <div class="col-md-12">
                    <div class="form-group">
                        <label for="youtube_link">Youtube Link</label>
                        {{-- <textarea class="form-control" placeholder="Enter youtube ink" name="video_gallery[{{ $loop->iteration }}][youtube_link]" id="youtube_link">{{  $item->youtube_link ?? ($item['youtube_link']) ? $item['youtube_link']: '' }}</textarea> --}}
                        <textarea class="form-control" placeholder="Enter youtube ink" name="video_gallery[{{ $loop->iteration }}][youtube_link]" id="youtube_link">{{ is_object($item) ? ($item->youtube_link ?? '') : (is_array($item) ? ($item['youtube_link'] ?? '') : '') }}</textarea>
                        @error("video_gallery.$loop->iteration.youtube_link") <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
                <!-- End Youtube Link -->

                <!-- new -->
                

                <div class="col-md-6">
                    <div class="form-group">
                        <label for="status">Status</label>

                            <select type="text" class="form-control" name="video_gallery[{{ $loop->iteration }}][status]" id="status">
                                <option value="1" {{ is_object($item) && ($item->status ?? '') == 1 ? ( 'selected' ?? '') : (is_array($item) && ($item['status'] ?? '') == 1 ? ( 'selected' ?? '') : '') }} >Active</option>
                                <option value="0" {{ is_object($item) && ($item->status ?? '') == 0 ? ( 'selected' ?? '') : (is_array($item) && ($item['status'] ?? '') == 0 ? ( 'selected' ?? '') : '') }} >Deactive</option>
                            </select>

                        @error("video_gallery.$loop->iteration.status") <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label for="order">Order</label>
                        <input type="number" class="form-control" placeholder="Enter video order" name="video_gallery[{{ $loop->iteration }}][order]" id="order" value="{{ is_object($item) ? ($item->order ?? '') : (is_array($item) ? ($item['order'] ?? '') : '') }}">
                        @error("video_gallery.$loop->iteration.order") <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
                <!-- End new -->
 

            </div>
        </div>
        <!-- End Static Faq Card -->

    @empty

        <!-- Static Video Gallery Card -->
        <div class="card p-4 video-gallery clone-template" data-clone-template-id="1">
            <div class="row item-row">
                
                {{-- new --}}
                <div class="col-md-12">
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" class="form-control" placeholder="Enter video title" name="video_gallery[1][title]" id="title" value="">
                        @error("video_gallery.1.title") <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
                {{-- new end --}}


                <!-- Iframe Link -->
                <div class="col-md-12">
                    <div class="form-group">
                        <label for="youtube_link">Youtube Link</label>
                        <textarea class="form-control" placeholder="Enter youtube link" name="video_gallery[1][youtube_link]" id="youtube_link"></textarea>
                        @error("video_gallery.1.youtube_link") <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
                <!-- End Iframe Link -->
                
                <!-- new -->
                

                <div class="col-md-6">
                    <div class="form-group">
                        <label for="status">Status</label>

                            <select type="text" class="form-control" name="video_gallery[1][status]" id="status">
                                <option value="1">Active</option>
                                <option value="0">Deactive</option>
                            </select>

                        @error("video_gallery.1.status") <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label for="order">Order</label>
                        <input type="number" class="form-control" placeholder="Enter video order" name="video_gallery[1][order]" id="order" value="">
                        @error("video_gallery.1.order") <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
                <!-- End new -->


            </div>
        </div>
        <!-- End Static Video Gallery Card -->

    @endforelse
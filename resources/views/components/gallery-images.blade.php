
    @section('component-style')
    <style>
        /* Gallery Images */
        #image-gallery-section #image-gallery-input {
            display: block;
        }

        #image-gallery-section .imageThumb {
            max-height: 100px;
            border: 1px solid;
            padding: 1px;
            cursor: pointer;
        }

        #image-gallery-section .item {
            position: relative;
            display: inline-block;
            margin: 10px 10px 0 0;
        }

        #image-gallery-section .remove {
            display: block;
            background: #525252a3;
            border-radius: 50%;
            height: 26px;
            width: 26px;
            color: white;
            text-align: center;
            cursor: pointer;
            position: absolute;
            top: 3px;
            right: 3px;
        }

        #image-gallery-section .remove:hover {
            background: white;
            color: black;
        }

        #image-gallery-section .fa-remove {
            font-size: 12px;
        }

        #image-gallery-section .drop-container {
            position: relative;
            display: flex;
            gap: 10px;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 200px;
            padding: 20px;
            border-radius: 10px;
            border: 2px dashed #555;
            color: #444;
            cursor: pointer;
            transition: background 0.2s ease-in-out, border 0.2s ease-in-out;
        }

        #image-gallery-section .drop-container:hover,
        #image-gallery-section .drop-container.drag-active {
            background: #eee;
            border-color: #111;
        }

        #image-gallery-section .drop-container:hover .drop-title,
        #image-gallery-section .drop-container.drag-active .drop-title {
            color: #222;
        }

        #image-gallery-section .drop-title {
            color: #444;
            font-size: 20px;
            font-weight: bold;
            text-align: center;
            transition: color 0.2s ease-in-out;
        }

        #image-gallery-section input[type="file"] {
            width: 350px;
            max-width: 100%;
            color: #444;
            padding: 5px;
            background: #fff;
            border-radius: 10px;
            border: 1px solid #555;
        }

        #image-gallery-section input[type="file"]::file-selector-button {
            margin-right: 20px;
            border: none;
            background: #084cdf;
            padding: 10px 20px;
            border-radius: 10px;
            color: #fff;
            cursor: pointer;
            transition: background 0.2s ease-in-out;
        }

        #image-gallery-section input[type="file"]::file-selector-button:hover {
            background: #0d45a5;
        }

        /* End Gallery Images */
    </style>
    @endsection

    <!-- Gallery Images -->
    <div class="row p-3">
        <div class="col-md-12 image-gallery-section" id="image-gallery-section">
            <div class="row">
                <div class="col-md-12">
                    <label for="">Gallery Images</label>
                    <label for="image-gallery-input" class="drop-container bg-white" id="dropcontainer">
                        <span class="drop-title">Drop files here</span>
                        or
                        <input type="file" id="image-gallery-input" name="gallery_images[]" accept="image/*" multiple />
                    </label>
                    <div id="image-gallery-preview">
                    @foreach ($galleryImages ?? [] as $galleryImage)
                        <span class="item">
                            <img class="imageThumb" src="{{ $galleryImage->full_url }}" title="{{ $galleryImage->name }}"/>
                            <span class="remove remove-gallery-image-btn" data-id="{{ $galleryImage->id }}">
                                <i class="fa fa-times"></i>
                            </span>
                        </span>
                    @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End Gallery Images -->

    @section('component-script')
    <script>
        //  Gallery Image Preview
        $(document).ready(function() {
            if (window.File && window.FileList && window.FileReader) {
                $("#image-gallery-input").on("change", function(e) {
                    var files = e.target.files,
                        filesLength = files.length;

                    var html_view = "";

                    for (var i = 0; i < filesLength; i++) {
                        var f = files[i];
                        var fileReader = new FileReader();

                        fileReader.onload = function(e) {
                            var file = e.target;

                            //   Html View
                            html_view = `<span class="item">
                                                                                                        <img class="imageThumb" src="${e.target.result}" />
                                                                                                        <span class="remove">
                                                                                                            <i class="fa fa-times"></i>
                                                                                                        </span>
                                                                                                    </span>`;
                            //   End Html View

                            $("#image-gallery-preview").append(html_view);

                        };
                        fileReader.readAsDataURL(f);
                    }
                });
            } else {
                alert("Your browser doesn't support to File API");
            }

            $(document).on('click', ".remove", function() {
                if(confirm("Are you sure?")){

                    if($(this).hasClass('remove-gallery-image-btn')){
                        var id          =   $(this).data('id');
                        var type        =   $(this).data('type');

                        // Ajax - Remove Gallery Image
                        $.ajax({
                                type: "post",
                                url: "{{ url('admin/remove-gallery-image') }}",
                                dataType: 'json',
                                data: {
                                    "_token": "{{ csrf_token() }}",
                                    id: id, 
                                    type: type 
                                },
                                success: function(data) {
                                    if (data.status) {
                                        // alert(data.message)
                                    } else {
                                        alert(data.message)
                                    }
                                },
                                error: function() {
                                    alert('Some Error Occured.');
                                }
                            });
                        // End Ajax - Remove Gallery Image

                    }
                    $(this).parent(".item").remove();
                }   
            });


        });
        //  End Gallery Image Preview
    </script>
    @endsection
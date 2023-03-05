<!DOCTYPE html>
<html lang="en" >
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="https://cdn.tailwindcss.com" />
        <script src="https://cdn.tailwindcss.com"></script>
        <title>Prism - API</title>

    </head>
    <body>
        <div class="sm:grid sm:grid-cols-2">
            <div class="overflow-hidden bg-black shadow mx-20 my-10 ">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-xl font-semibold leading-6 text-white">Prism-API</h3>
                    <p class="mt-1 max-w-2xl text-sm text-white">List of API</p>
                </div>
                <div class="border-t bg-gray-900 border-gray-200">
                    <div class="border-t border-gray-200">
                        <dl>
                            <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-lg  font-bold font-medium text-black p-2">List of API Routes</dt>
                                <dd class="mt-1  font-bold text-lg text-black sm:col-span-2 p-2 sm:mt-0">
                                    Link
                                </dd>
                                <dt class="text-sm font-medium text-gray-500">"with" parameter:</dt>
                                <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0"><code>GET /api/users?with=role</code></dd>
                                <dt class="text-sm font-medium text-gray-500">"orderBy" parameter:</dt>
                                <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0"><code>GET /api/users?orderBy=name:asc</code></dd>
                                <dt class="text-sm font-medium text-gray-500">"filter" parameter:</dt>
                                <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0"><code>GET /api/users?filter=email:user@example.com</code></dd>
                                <dt class="text-sm font-medium text-gray-500">"limit" parameter:</dt>
                                <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0"><code>GET /api/users?limit=10</code></dd>
                                <dt class="text-sm font-medium text-gray-500">"fields" parameter:</dt>
                                <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0"><code>GET /api/users?fields=name,email</code></dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>

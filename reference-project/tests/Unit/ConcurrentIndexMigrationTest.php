<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Tests\Support\Narrow;
use Tests\Support\Unit\ConcurrentIndexMigrationHelpers;

uses()->group('arch');

dataset('blocking index migrations', [
    'index on an existing table' => ["Schema::table('products', function (Blueprint \$table): void {\n    \$table->index('sku');\n});", '', ['products']],
    'unique column on an existing table' => ["Schema::table('coupons', fn (Blueprint \$table) => \$table->string('code')->unique());", '', ['coupons']],
    'foreign key on an existing table' => ["Schema::table('orders', function (Blueprint \$table): void {\n    \$table->foreignId('carrier_id')->nullable()->constrained();\n});", '', ['orders']],
    'explicit foreign on an existing table' => ["Schema::table('orders', function (Blueprint \$table): void {\n    \$table->foreign('carrier_id')->references('id')->on('carriers');\n});", '', ['orders']],
    'morphs on an existing table' => ["Schema::table('notifications', fn (Blueprint \$table) => \$table->nullableMorphs('subject'));", '', ['notifications']],
    'primary key on an existing table' => ["Schema::table('category_product', fn (Blueprint \$table) => \$table->primary(['category_id', 'product_id']));", '', ['category_product']],
    'method name in another case' => ["Schema::table('products', fn (Blueprint \$table) => \$table->Index('brand'));", '', ['products']],
    'schema from a connection' => ["Schema::connection('landlord')->table('events', function (Blueprint \$table): void {\n    \$table->index('name');\n});", '', ['events']],
    'blueprint index next to a concurrent one' => ["DB::statement('CREATE INDEX CONCURRENTLY products_sku_index ON products (sku)');\nSchema::table('products', fn (Blueprint \$table) => \$table->index('brand'));", 'public $withinTransaction = false;', ['products']],
    'index in a helper method' => ['$this->addIndex();', "private function addIndex(): void\n{\n    Schema::table('users', fn (Blueprint \$table) => \$table->index('email'));\n}", ['users']],
    'raw index on an existing table' => ["DB::statement('CREATE INDEX products_brand_index ON products (brand)');", '', ['products']],
    'raw unique index on an existing table' => ["DB::statement(<<<'SQL'\n    create unique index users_email_unique on public.users (lower(email))\n    SQL);", '', ['users']],
    'raw foreign key that is validated at once' => ["DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_carrier_id_foreign FOREIGN KEY (carrier_id) REFERENCES carriers (id)');", 'public $withinTransaction = false;', ['orders']],
]);

it('finds a blocking index on a table that an earlier migration created', function (string $up, string $members, array $tables): void {
    expect(ConcurrentIndexMigrationHelpers::blockingIndexTables(ConcurrentIndexMigrationHelpers::indexMigrationSource($up, $members)))->toBe($tables);
})->with('blocking index migrations');

dataset('safe index migrations', [
    'index on a table created in the same migration' => ["Schema::create('warehouses', function (Blueprint \$table): void {\n    \$table->id();\n});\nSchema::table('warehouses', function (Blueprint \$table): void {\n    \$table->index('name');\n});"],
    'raw index on a table created in the same migration' => ["DB::statement('CREATE TABLE stock_moves (id bigint PRIMARY KEY, warehouse_id bigint REFERENCES warehouses (id))');\nDB::statement('CREATE INDEX stock_moves_warehouse_id_index ON stock_moves (warehouse_id)');"],
    'column without an index on an existing table' => ["Schema::table('products', function (Blueprint \$table): void {\n    \$table->string('brand')->nullable();\n});"],
    'concurrent index outside a transaction' => ["DB::statement('CREATE INDEX CONCURRENTLY products_brand_index ON products (brand)');", 'public $withinTransaction = false;'],
    'foreign key added NOT VALID and validated afterwards' => ["DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_carrier_id_foreign FOREIGN KEY (carrier_id) REFERENCES carriers (id) NOT VALID');\nDB::statement('ALTER TABLE orders VALIDATE CONSTRAINT orders_carrier_id_foreign');", 'public $withinTransaction = false;'],
    'index restored in down' => ['', "public function down(): void\n{\n    Schema::table('products', fn (Blueprint \$table) => \$table->index('brand'));\n}"],
]);

it('accepts an index that blocks no existing table', function (string $up, string $members = ''): void {
    expect(ConcurrentIndexMigrationHelpers::blockingIndexTables(ConcurrentIndexMigrationHelpers::indexMigrationSource($up, $members)))->toBe([]);
})->with('safe index migrations');

it('builds indexes on existing tables concurrently in every new migration', function (): void {
    $offenders = collect(Narrow::strings((new Filesystem())->glob(dirname(__DIR__, 2) . '/database/migrations/*.php')))
        ->filter(fn (string $path): bool => strcmp(basename($path), '2025_01_01') >= 0)
        ->mapWithKeys(fn (string $path): array => [basename($path) => ConcurrentIndexMigrationHelpers::blockingIndexTables((string) file_get_contents($path))])
        ->filter()
        ->all();

    expect($offenders)->toBe([]);
});

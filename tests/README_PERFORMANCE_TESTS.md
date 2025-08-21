# Search Service Performance Tests

This directory contains comprehensive performance, stress, and benchmarking tests for the ProductSearchService using PostgreSQL as the testing database.

## Test Files Overview

### 1. ProductSearchPerformanceTest.php
- **Purpose**: Basic performance testing with different dataset sizes
- **Tests**: Search performance with 100, 1K, 5K products
- **Metrics**: Execution time, memory usage, query optimization
- **Key Features**: 
  - Baseline performance measurements
  - Memory usage tracking
  - Cache performance analysis
  - Database query count optimization

### 2. ProductSearchStressTest.php  
- **Purpose**: Stress testing under high load conditions
- **Tests**: Concurrent requests, high volume searches, memory stress
- **Metrics**: Throughput, success rates, resource utilization
- **Key Features**:
  - Concurrent search request simulation
  - High volume request testing (50+ requests)
  - Database connection stress testing
  - Cache invalidation under stress
  - PostgreSQL full-text search performance

### 3. ProductSearchBenchmarkTest.php
- **Purpose**: Detailed benchmarking with statistical analysis
- **Tests**: Comprehensive performance baselines and comparisons  
- **Metrics**: Statistical analysis (median, std dev, percentiles)
- **Key Features**:
  - Multi-dataset benchmarking (100 to 2500 products)
  - Pagination performance analysis
  - Complex filter combination benchmarking
  - Search ranking quality vs performance analysis
  - PostgreSQL-specific feature benchmarking

### 4. ProductSearchMemoryTest.php
- **Purpose**: Memory usage analysis and leak detection
- **Tests**: Memory efficiency, leak detection, large dataset handling
- **Metrics**: Memory consumption, scaling efficiency, garbage collection
- **Key Features**:
  - Incremental dataset growth memory profiling
  - Memory leak detection in repetitive operations
  - Large result set memory handling
  - Cache memory efficiency analysis

## Prerequisites

### 1. PostgreSQL Testing Database
```bash
# Create the testing database
createdb testing_database

# Connect and enable required extensions
psql -d testing_database
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;
```

### 2. Environment Configuration
Ensure your `.env.testing` file is configured with PostgreSQL connection:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=testing_database
DB_USERNAME=postgres
DB_PASSWORD=your_password

# Use faster cache/session for testing
CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
```

### 3. Database Migrations
Run migrations on the testing database:
```bash
php artisan migrate --env=testing
```

## Running the Tests

### Run Individual Test Suites

```bash
# Performance Tests (Basic performance metrics)
php artisan test tests/Feature/ProductSearchPerformanceTest.php --env=testing

# Stress Tests (High load scenarios)  
php artisan test tests/Feature/ProductSearchStressTest.php --env=testing

# Benchmark Tests (Detailed statistical analysis)
php artisan test tests/Feature/ProductSearchBenchmarkTest.php --env=testing

# Memory Tests (Memory usage and leak detection)
php artisan test tests/Feature/ProductSearchMemoryTest.php --env=testing
```

### Run All Performance Tests
```bash
php artisan test tests/Feature/ProductSearch*Test.php --env=testing
```

### Run Specific Test Cases
```bash
# Run only memory usage tests
php artisan test --filter="memory usage" --env=testing

# Run only PostgreSQL specific tests  
php artisan test --filter="postgresql" --env=testing

# Run only stress tests
php artisan test --filter="stress" --env=testing
```

## Understanding Test Results

### Performance Metrics Explained

#### Response Time Benchmarks
- **Small Dataset (100 products)**: < 300ms average
- **Medium Dataset (1K products)**: < 600ms average  
- **Large Dataset (5K products)**: < 1500ms average

#### Memory Usage Expectations
- **Search Operations**: < 10MB increase per search
- **Large Datasets**: < 120MB total for 5K products
- **Cache Operations**: 50%+ improvement over cache miss

#### Throughput Requirements
- **Concurrent Requests**: > 5 requests/second
- **Success Rate**: > 95% under stress
- **Database Connections**: < 5% connection errors

### Sample Output Interpretation

```
Performance Test Results:
✓ Small dataset performance: 245ms avg, 2.3MB memory
✓ Medium dataset performance: 567ms avg, 8.1MB memory  
✓ Pagination performance: 456ms avg across 10 pages
✓ Cache performance: 78% improvement on cache hit

Stress Test Results:
✓ Concurrent requests: 10/10 succeeded, 8.5 req/sec
✓ High volume test: 48/50 succeeded (96% success rate)
✓ Memory stress: No leaks detected over 50 iterations

Benchmark Results:
✓ Search ranking quality: 34.2 relevance score, 623ms avg
✓ Complex filters: 987ms avg for all filters combined
✓ PostgreSQL features: Full-text search 387ms avg
```

## Performance Optimization Tips

### Database Optimizations
1. **Indexes**: Ensure proper indexes on searchable columns
2. **Search Vector**: PostgreSQL search_vector should be properly maintained
3. **Connection Pooling**: Use connection pooling for production

### Application Optimizations  
1. **Caching**: Implement Redis for production caching
2. **Pagination**: Use cursor-based pagination for large datasets
3. **Eager Loading**: Prevent N+1 queries with proper relationships

### Search Service Optimizations
1. **Query Building**: Use optimized query builders
2. **Result Limiting**: Implement reasonable result limits
3. **Filter Optimization**: Cache filter options aggressively

## Troubleshooting

### Common Issues

#### "CREATE EXTENSION" Errors
```bash
# Solution: Enable extensions in PostgreSQL
psql -d testing_database -c "CREATE EXTENSION IF NOT EXISTS pg_trgm;"
```

#### Memory Limit Errors
```bash
# Solution: Increase PHP memory limit
php -d memory_limit=512M artisan test
```

#### Connection Timeout Issues
```bash
# Solution: Increase database timeout in config/database.php
'options' => [
    PDO::ATTR_TIMEOUT => 30,
]
```

#### Test Database Not Found
```bash
# Solution: Create the testing database
createdb testing_database
php artisan migrate --env=testing
```

## Continuous Integration

### GitHub Actions Example
```yaml
- name: Setup PostgreSQL
  run: |
    sudo systemctl start postgresql
    sudo -u postgres createdb testing_database
    sudo -u postgres psql -d testing_database -c "CREATE EXTENSION pg_trgm;"

- name: Run Performance Tests
  run: |
    php artisan test tests/Feature/ProductSearch*Test.php --env=testing
```

## Performance Monitoring

### Setting Up Monitoring
1. Use Laravel Telescope for query analysis
2. Implement custom metrics collection
3. Set up alerts for performance degradation
4. Monitor memory usage in production

### Key Metrics to Track
- Average search response time
- 95th percentile response time  
- Memory usage per search operation
- Cache hit ratio
- Database query count per search
- Concurrent user capacity

## Best Practices

1. **Run tests on dedicated testing database**
2. **Monitor memory usage during large tests**
3. **Use realistic test data volumes**
4. **Test with production-like PostgreSQL configuration**
5. **Run performance tests regularly in CI/CD**
6. **Set performance budgets and fail builds on regression**

## Contributing

When adding new performance tests:

1. Follow existing test structure and naming conventions
2. Include proper assertions for performance expectations
3. Add comprehensive documentation for new metrics
4. Test with multiple dataset sizes
5. Include both positive and negative test cases
6. Document expected performance baselines
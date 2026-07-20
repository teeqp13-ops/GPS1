#import <Foundation/Foundation.h>
NS_ASSUME_NONNULL_BEGIN
@interface GPFavoritesStore : NSObject
+ (instancetype)shared;
- (NSArray<NSDictionary *> *)all;
- (void)addName:(NSString *)name latitude:(double)latitude longitude:(double)longitude;
- (void)removeAtIndex:(NSUInteger)index;
@end
NS_ASSUME_NONNULL_END

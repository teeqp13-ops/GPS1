#import <UIKit/UIKit.h>
#import "GPOverlayController.h"

static UIButton *GPButton;

static UIViewController *GPTopController(void) {
    UIWindow *window = nil;
    for (UIScene *scene in UIApplication.sharedApplication.connectedScenes) {
        if (scene.activationState != UISceneActivationStateForegroundActive || ![scene isKindOfClass:UIWindowScene.class]) continue;
        for (UIWindow *candidate in ((UIWindowScene *)scene).windows) {
            if (candidate.isKeyWindow) { window = candidate; break; }
        }
    }
    if (!window) window = UIApplication.sharedApplication.windows.firstObject;
    UIViewController *vc = window.rootViewController;
    while (vc.presentedViewController) vc = vc.presentedViewController;
    return vc;
}

@interface GPLauncher : NSObject
+ (instancetype)shared;
- (void)openPanel;
@end

@implementation GPLauncher
+ (instancetype)shared { static id x; static dispatch_once_t once; dispatch_once(&once, ^{ x=[self new]; }); return x; }
- (void)openPanel {
    UIViewController *top = GPTopController();
    if (!top) return;
    GPOverlayController *vc = [GPOverlayController new];
    UINavigationController *nav = [[UINavigationController alloc] initWithRootViewController:vc];
    nav.modalPresentationStyle = UIModalPresentationFullScreen;
    [top presentViewController:nav animated:YES completion:nil];
}
@end

static void GPInstallButton(void) {
    if (GPButton) return;
    UIWindow *window = nil;
    for (UIWindow *candidate in UIApplication.sharedApplication.windows) {
        if (candidate.isKeyWindow) { window = candidate; break; }
    }
    if (!window) window = UIApplication.sharedApplication.windows.firstObject;
    if (!window) return;

    GPButton = [UIButton buttonWithType:UIButtonTypeSystem];
    GPButton.frame = CGRectMake(18, 160, 54, 54);
    GPButton.layer.cornerRadius = 27;
    GPButton.backgroundColor = [UIColor colorWithWhite:0.1 alpha:0.85];
    [GPButton setTitle:@"GPS" forState:UIControlStateNormal];
    [GPButton setTitleColor:UIColor.whiteColor forState:UIControlStateNormal];
    [GPButton addTarget:[GPLauncher shared] action:@selector(openPanel) forControlEvents:UIControlEventTouchUpInside];
    [window addSubview:GPButton];
}

%ctor {
    dispatch_after(dispatch_time(DISPATCH_TIME_NOW, (int64_t)(2 * NSEC_PER_SEC)), dispatch_get_main_queue(), ^{
        GPInstallButton();
    });
}
